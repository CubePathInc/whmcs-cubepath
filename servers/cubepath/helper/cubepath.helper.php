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
         * Dropdown options (project id => name) from the GET /projects/ response.
         * A saved project that is no longer listed is kept so saving the product
         * does not silently change it.
         *
         * @param array  $projects entries with a nested "project" object
         * @param string $current  project id currently saved
         * @return array
         */
        public static function projectOptions(array $projects, $current)
        {
            $names = array();
            foreach ($projects as $entry)
            {
                $project = isset($entry['project']) && is_array($entry['project']) ? $entry['project'] : $entry;
                if (isset($project['id']))
                {
                    $id = (string)$project['id'];
                    $names[$id] = isset($project['name']) && $project['name'] !== '' ? (string)$project['name'] : 'Project ' . $id;
                }
            }
            asort($names, SORT_NATURAL | SORT_FLAG_CASE);

            $options = array('' => '-- Select a project --') + $names;
            if ($current !== '' && !isset($options[$current]))
            {
                $options[$current] = 'Project ' . $current . ' (not found)';
            }

            return $options;
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
