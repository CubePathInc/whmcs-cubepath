<?php
/**
 * CubePath Cloud WHMCS Module - Helper Functions
 *
 * Static utility methods for custom field management, configurable option
 * lookups, and data parsing for CubePath API responses.
 */

use Illuminate\Database\Capsule\Manager as Capsule;

if (!class_exists('CubepathHelper'))
{
    class CubepathHelper
    {
        /**
         * Power actions exposed by the module mapped to the API power types.
         */
        const POWER_ACTIONS = array(
            'start'  => 'start_vps',
            'stop'   => 'stop_vps',
            'reboot' => 'restart_vps',
        );

        /**
         * Run a power action on a VPS.
         *
         * @param \Cubepath\CubepathClient $client
         * @param int                       $vpsId
         * @param string                    $action start, stop or reboot
         * @return array API response
         */
        public static function power($client, $vpsId, $action)
        {
            if (!isset(self::POWER_ACTIONS[$action]))
            {
                throw new \InvalidArgumentException('Unknown power action: ' . $action);
            }

            return $client->vps()->power((int)$vpsId, self::POWER_ACTIONS[$action]);
        }

        /**
         * API token for a product or service: the product's own token in
         * configoption1, otherwise the password of its CubePath server.
         *
         * @param array $params WHMCS module parameters
         * @return string
         */
        public static function apiToken(array $params)
        {
            $token = isset($params['configoption1']) ? trim((string)$params['configoption1']) : '';
            if ($token === '' && isset($params['serverpassword']))
            {
                $token = trim((string)$params['serverpassword']);
            }

            return $token;
        }

        /**
         * API client for a product or service.
         *
         * @param array $params WHMCS module parameters
         * @return \Cubepath\CubepathClient
         * @throws \RuntimeException When no API token is configured
         */
        public static function client(array $params)
        {
            $token = self::apiToken($params);
            if ($token === '')
            {
                throw new \RuntimeException('No CubePath API token. Add a CubePath server in System Settings > Servers and assign its group to the product.');
            }

            // One client per token, so lists fetched through it are shared in the request.
            static $clients = array();
            if (!isset($clients[$token]))
            {
                $clients[$token] = new \Cubepath\CubepathClient($token);
            }

            return $clients[$token];
        }

        /**
         * Markup that mounts the panel (ui/, built into assets/dist/app.js), or
         * the order form cards (assets/dist/store.js). The config is read by
         * the script; see ui/src/lib/types.ts PanelConfig and StoreConfig.
         *
         * @param array  $config
         * @param string $bundle
         * @return string
         */
        public static function panelHtml(array $config, $bundle = 'app.js')
        {
            $script = CUBEPATHDIR . 'assets' . DS . 'dist' . DS . $bundle;
            if (!file_exists($script))
            {
                return '<div class="alert alert-warning">CubePath panel assets are missing. Upload the complete module from the release archive.</div>';
            }

            $src = self::systemUrl() . 'modules/servers/cubepath/assets/dist/' . $bundle . '?v=' . filemtime($script);

            // Base64 keeps the config intact: the client area decodes HTML entities in module output.
            return '<div data-cubepath-panel="' . base64_encode(json_encode($config)) . '"></div>'
                . '<script src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" defer></script>';
        }

        /**
         * WHMCS System URL with a trailing slash.
         *
         * @return string
         */
        public static function systemUrl()
        {
            $url = '';
            if (class_exists('\WHMCS\Config\Setting'))
            {
                $url = (string)\WHMCS\Config\Setting::getValue('SystemURL');
            }

            return rtrim($url, '/') . '/';
        }

        /**
         * Whether a request carries the session's CSRF token.
         *
         * @param string $token
         * @return bool
         */
        public static function validToken($token)
        {
            $expected = (string)generate_token('plain');

            return $expected !== '' && is_string($token) && hash_equals($expected, $token);
        }

        /**
         * Language of the client viewing the client area.
         *
         * @param array $params
         * @return string WHMCS language name, e.g. "spanish"
         */
        public static function clientLanguage(array $params)
        {
            if (!empty($_SESSION['Language']))
            {
                return (string)$_SESSION['Language'];
            }
            if (!empty($params['clientsdetails']['language']))
            {
                return (string)$params['clientsdetails']['language'];
            }

            return class_exists('\WHMCS\Config\Setting') ? (string)\WHMCS\Config\Setting::getValue('Language') : 'english';
        }

        /**
         * Every VPS the token can see (GET /vps/), indexed by id. Cached per
         * client for the request; client() reuses one client per token, so
         * listing many services costs one call per token.
         *
         * @param \Cubepath\CubepathClient $client
         * @return array<int, array>
         */
        public static function allVps($client)
        {
            static $cache = array();
            $key = spl_object_hash($client);
            if (!isset($cache[$key]))
            {
                $cache[$key] = array();
                foreach ($client->vps()->listAll() as $vps)
                {
                    if (isset($vps['id']))
                    {
                        $cache[$key][(int)$vps['id']] = $vps;
                    }
                }
            }

            return $cache[$key];
        }

        /**
         * One VPS from GET /vps/, or null when the token cannot see it.
         *
         * @param \Cubepath\CubepathClient $client
         * @param int                       $vpsId
         * @return array|null
         */
        public static function findVps($client, $vpsId)
        {
            $all = self::allVps($client);

            return isset($all[(int)$vpsId]) ? $all[(int)$vpsId] : null;
        }

        /**
         * The fields of a VPS the panels show (ui/src/lib/types.ts Vps).
         *
         * @param array $vps GET /vps/ entry
         * @return array
         */
        public static function normalizeVps(array $vps)
        {
            $ipv6 = null;
            foreach (isset($vps['floating_ips']['list']) ? $vps['floating_ips']['list'] : array() as $ip)
            {
                if ($ipv6 === null && isset($ip['type'], $ip['address']) && $ip['type'] === 'IPv6')
                {
                    $ipv6 = $ip['address'];
                }
            }

            return array(
                'id'         => (int)$vps['id'],
                'name'       => (string)$vps['name'],
                'label'      => isset($vps['label']) ? $vps['label'] : null,
                'hostname'   => isset($vps['hostname']) ? $vps['hostname'] : null,
                'status'     => (string)$vps['status'],
                'user'       => isset($vps['user']) ? $vps['user'] : null,
                'plan'       => isset($vps['plan']['plan_name']) ? array(
                    'plan_name' => (string)$vps['plan']['plan_name'],
                    'ram'       => (int)$vps['plan']['ram'],
                    'cpu'       => (int)$vps['plan']['cpu'],
                    'storage'   => (int)$vps['plan']['storage'],
                    'bandwidth' => (float)$vps['plan']['bandwidth'],
                ) : null,
                'template'   => isset($vps['template']['template_name']) ? array(
                    'template_name' => (string)$vps['template']['template_name'],
                    'os_name'       => isset($vps['template']['os_name']) ? (string)$vps['template']['os_name'] : (string)$vps['template']['template_name'],
                ) : null,
                'location'   => isset($vps['location']['location_name']) ? array(
                    'description'   => isset($vps['location']['description']) ? (string)$vps['location']['description'] : (string)$vps['location']['location_name'],
                    'location_name' => (string)$vps['location']['location_name'],
                ) : null,
                'ipv4'       => self::primaryIpv4($vps),
                'ipv6'       => $ipv6,
                'private_ip' => !empty($vps['network']['assigned_ip']) ? (string)$vps['network']['assigned_ip'] : null,
            );
        }

        /**
         * Primary IPv4 address of a VPS as returned in the project listing
         * (floating_ips.list[] entries with address, type and is_primary).
         *
         * @param array $vps
         * @return string|null
         */
        public static function primaryIpv4(array $vps)
        {
            $ips = isset($vps['floating_ips']['list']) && is_array($vps['floating_ips']['list']) ? $vps['floating_ips']['list'] : array();
            $fallback = null;

            foreach ($ips as $ip)
            {
                if (empty($ip['address']) || (isset($ip['type']) && $ip['type'] !== 'IPv4'))
                {
                    continue;
                }
                if (!empty($ip['is_primary']))
                {
                    return $ip['address'];
                }
                if ($fallback === null)
                {
                    $fallback = $ip['address'];
                }
            }

            return $fallback;
        }

        /**
         * Get a custom field value for a given service.
         *
         * Looks up the custom field by matching the field name prefix (before the pipe character).
         *
         * @param int    $serviceId Service (hosting) ID
         * @param string $fieldName Field name prefix (e.g., 'vps_id')
         * @return string|null Field value or null if not found
         */
        public static function getCustomFieldValue($serviceId, $fieldName)
        {
            // Get the service to find the product ID
            $service = Capsule::table('tblhosting')->where('id', $serviceId)->first();
            if (!$service)
            {
                return null;
            }

            $customField = Capsule::table('tblcustomfields')
                ->where('type', 'product')
                ->where('relid', $service->packageid)
                ->where('fieldname', 'LIKE', $fieldName . '|%')
                ->first();

            if (!$customField)
            {
                return null;
            }

            $fieldValue = Capsule::table('tblcustomfieldsvalues')
                ->where('fieldid', $customField->id)
                ->where('relid', $serviceId)
                ->first();

            return $fieldValue ? $fieldValue->value : null;
        }

        /**
         * Set (or create) a custom field value for a given service.
         *
         * @param int    $serviceId Service (hosting) ID
         * @param string $fieldName Field name prefix (e.g., 'vps_id')
         * @param string $value     Value to store
         * @return bool True if the value was set successfully
         */
        public static function setCustomFieldValue($serviceId, $fieldName, $value)
        {
            $service = Capsule::table('tblhosting')->where('id', $serviceId)->first();
            if (!$service)
            {
                return false;
            }

            $customField = Capsule::table('tblcustomfields')
                ->where('type', 'product')
                ->where('relid', $service->packageid)
                ->where('fieldname', 'LIKE', $fieldName . '|%')
                ->first();

            if (!$customField)
            {
                return false;
            }

            $existing = Capsule::table('tblcustomfieldsvalues')
                ->where('fieldid', $customField->id)
                ->where('relid', $serviceId)
                ->first();

            if ($existing)
            {
                Capsule::table('tblcustomfieldsvalues')
                    ->where('fieldid', $customField->id)
                    ->where('relid', $serviceId)
                    ->update(array('value' => $value));
            }
            else
            {
                Capsule::table('tblcustomfieldsvalues')->insert(array(
                    'fieldid' => $customField->id,
                    'relid'   => $serviceId,
                    'value'   => $value,
                ));
            }

            return true;
        }

        /**
         * Get a product configuration option value (configoption1, configoption2, etc.).
         *
         * @param int    $productId Product ID
         * @param string $field     Column name (e.g., 'configoption1') or 'all' for all columns
         * @param string $default   Default value if the field is not found
         * @return mixed Field value, full result object, or default value
         */
        public static function getProductConfigOption($productId, $field = 'all', $default = '')
        {
            $result = Capsule::table('tblproducts')->where('id', $productId)->first();

            if ($field == 'all')
            {
                return $result;
            }

            if ($result && isset($result->{$field}))
            {
                return $result->{$field};
            }

            return $default;
        }

        /**
         * Get a configurable option value for a specific service.
         *
         * Reads from tblhostingconfigoptions joined with tblproductconfigoptions
         * to find the value of a named configurable option.
         *
         * @param int    $serviceId  Service (hosting) ID
         * @param string $optionName Option name prefix (before the pipe character)
         * @return string|null Option value or null if not found
         */
        public static function getConfigurableOptionValue($serviceId, $optionName)
        {
            $result = Capsule::table('tblhostingconfigoptions as hco')
                ->join('tblproductconfigoptions as pco', 'hco.configid', '=', 'pco.id')
                ->join('tblproductconfigoptionssub as pcos', 'hco.optionid', '=', 'pcos.id')
                ->where('hco.relid', $serviceId)
                ->where('pco.optionname', 'LIKE', $optionName . '|%')
                ->select('pcos.optionname')
                ->first();

            if ($result && isset($result->optionname))
            {
                // Sub-options are named "<api value>|<label>"; WHMCS only shows the label to clients
                $parts = explode('|', $result->optionname, 2);
                return trim($parts[0]);
            }

            return null;
        }

        /**
         * Quantity of a service's configurable option: 1 or 0 for a yes/no
         * option such as backups.
         *
         * @param int    $serviceId
         * @param string $optionName Option name prefix (before the pipe)
         * @return int|null Null if the service has no such option
         */
        public static function getConfigurableOptionQty($serviceId, $optionName)
        {
            $qty = Capsule::table('tblhostingconfigoptions as hco')
                ->join('tblproductconfigoptions as pco', 'hco.configid', '=', 'pco.id')
                ->where('hco.relid', $serviceId)
                ->where('pco.optionname', 'LIKE', $optionName . '|%')
                ->value('hco.qty');

            return $qty === null ? null : (int)$qty;
        }

        /**
         * Whether a service may use automatic backups. Products that do not
         * sell backups (no visible backups option) leave them free as before.
         *
         * @param int $serviceId
         * @return bool|null Null when backups are not sold for the product
         */
        public static function backupsPurchased($serviceId)
        {
            $sold = Capsule::table('tblhosting as h')
                ->join('tblproductconfiglinks as l', 'l.pid', '=', 'h.packageid')
                ->join('tblproductconfigoptions as o', 'o.gid', '=', 'l.gid')
                ->where('h.id', $serviceId)
                ->where('o.optionname', 'LIKE', 'backups|%')
                ->where('o.hidden', 0)
                ->exists();

            if (!$sold)
            {
                return null;
            }

            return (int)self::getConfigurableOptionQty($serviceId, 'backups') > 0;
        }

        /**
         * Delete an SSH key the module added for a service. Fails while a
         * VPS still uses it.
         *
         * @param \Cubepath\CubepathClient $client
         * @param int                       $keyId
         * @return bool Whether it is gone
         */
        public static function deleteSshKey($client, $keyId)
        {
            try
            {
                $client->sshKeys()->delete((int)$keyId);

                return true;
            }
            catch (\Cubepath\APIError $e)
            {
                return $e->getStatusCode() === 404;
            }
            catch (\Exception $e)
            {
                return false;
            }
        }

        /**
         * Whether a string is one OpenSSH public key the API accepts.
         *
         * @param string $key
         * @return bool
         */
        public static function validSshKey($key)
        {
            return (bool)preg_match('/^(ssh-rsa|ssh-ed25519|ecdsa-sha2-nistp256) AAAA[0-9A-Za-z+\/]+={0,3}([ \t][^\x00-\x1f]*)?$/D', trim((string)$key));
        }

        /**
         * Point a service's configurable option at the sub-option whose API
         * value is $value (e.g. the new OS after a reinstall). Does nothing if
         * the product has no such option or value.
         *
         * @param int    $serviceId
         * @param string $optionName Option name prefix (before the pipe)
         * @param string $value      API value (before the pipe of the sub-option)
         */
        public static function setConfigurableOptionValue($serviceId, $optionName, $value)
        {
            $row = Capsule::table('tblhostingconfigoptions as hco')
                ->join('tblproductconfigoptions as pco', 'hco.configid', '=', 'pco.id')
                ->where('hco.relid', $serviceId)
                ->where('pco.optionname', 'LIKE', $optionName . '|%')
                ->first(array('hco.id', 'hco.configid'));

            if (!$row)
            {
                return;
            }

            $subId = Capsule::table('tblproductconfigoptionssub')
                ->where('configid', $row->configid)
                ->where('optionname', 'LIKE', str_replace(array('%', '_'), array('\%', '\_'), $value) . '|%')
                ->value('id');

            if ($subId)
            {
                Capsule::table('tblhostingconfigoptions')->where('id', $row->id)->update(array('optionid' => $subId));
            }
        }

        /**
         * Create standard custom fields for a CubePath product.
         *
         * Fields created: vps_id, ip_address, project_id
         *
         * @param int $productId Product ID
         */
        public static function addCustomFields($productId)
        {
            $fields = array(
                'vps_id|VPS ID',
                'ip_address|IP Address',
                'project_id|Project ID',
            );

            foreach ($fields as $fieldName)
            {
                $existing = Capsule::table('tblcustomfields')
                    ->where('type', 'product')
                    ->where('relid', $productId)
                    ->where('fieldname', $fieldName)
                    ->first();

                if (!$existing)
                {
                    Capsule::table('tblcustomfields')->insert(array(
                        'type'      => 'product',
                        'relid'     => $productId,
                        'fieldname' => $fieldName,
                        'fieldtype' => 'text',
                        'adminonly' => 'on',
                    ));
                }
            }
        }

        /**
         * Parse CubePath pricing data into a key=>value array for dropdown use.
         *
         * @param array $pricingData Pricing API response
         * @return array Plan names mapped to display labels
         */
        public static function parsePlans($pricingData)
        {
            $plans = array();

            if (!is_array($pricingData))
            {
                return $plans;
            }

            foreach ($pricingData as $plan)
            {
                if (isset($plan['plan_name']))
                {
                    $label = $plan['plan_name'];

                    // Include resource details if available
                    if (isset($plan['vcpu']))
                    {
                        $label .= ' - ' . $plan['vcpu'] . ' vCPU';
                    }
                    if (isset($plan['ram_mb']))
                    {
                        $label .= ', ' . ($plan['ram_mb'] >= 1024 ? round($plan['ram_mb'] / 1024) . 'GB' : $plan['ram_mb'] . 'MB') . ' RAM';
                    }
                    if (isset($plan['disk_gb']))
                    {
                        $label .= ', ' . $plan['disk_gb'] . 'GB Disk';
                    }
                    if (isset($plan['price_monthly']))
                    {
                        $label .= ' - $' . $plan['price_monthly'] . '/mo';
                    }

                    $plans[$plan['plan_name']] = $label;
                }
            }

            return $plans;
        }

        /**
         * Extract unique locations from pricing data for dropdown use.
         *
         * @param array $pricingData Pricing API response
         * @return array Location names mapped to display labels
         */
        public static function parseLocations($pricingData)
        {
            $locations = array();

            if (!is_array($pricingData))
            {
                return $locations;
            }

            foreach ($pricingData as $item)
            {
                if (isset($item['location_name']) && !isset($locations[$item['location_name']]))
                {
                    $label = $item['location_name'];
                    if (isset($item['location_label']))
                    {
                        $label = $item['location_label'] . ' (' . $item['location_name'] . ')';
                    }
                    $locations[$item['location_name']] = $label;
                }
            }

            return $locations;
        }

        /**
         * Format OS templates for dropdown use.
         *
         * @param array $templates Templates API response
         * @return array Template names mapped to display labels
         */
        public static function parseTemplates($templates)
        {
            $parsed = array();

            if (!is_array($templates))
            {
                return $parsed;
            }

            // Handle the templates response structure
            $templateList = $templates;
            if (isset($templates['operating_systems']))
            {
                $templateList = $templates['operating_systems'];
            }

            foreach ($templateList as $template)
            {
                if (isset($template['name']))
                {
                    $key = isset($template['template_name']) ? $template['template_name'] : $template['name'];
                    $label = $template['name'];

                    if (isset($template['version']))
                    {
                        $label .= ' ' . $template['version'];
                    }

                    $parsed[$key] = $label;
                }
            }

            return $parsed;
        }
    }
}
