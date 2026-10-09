<?php
/**
 * CubePath Cloud WHMCS Module - Panel API
 *
 * JSON actions behind the client area and admin panels (ui/). Every action is
 * scoped to one WHMCS service: the VPS is always the one stored in the
 * service's vps_id field, never an id sent by the browser.
 */

use Illuminate\Database\Capsule\Manager as Capsule;

if (!class_exists('PanelController'))
{
    class PanelController
    {
        const ACTOR_CLIENT = 'client';
        const ACTOR_ADMIN  = 'admin';

        const FIREWALL_TABLE = 'mod_cubepath_firewall';
        const MAX_FIREWALL_RULES = 100;

        const METRIC_RANGES = array('H1', 'H3', 'H6', 'H12', 'H24', 'D3', 'D7', 'D30');
        const METRICS = array('CPU_USAGE', 'MEMORY_USAGE', 'DISK_READ', 'DISK_WRITE', 'NETWORK_RECEIVE', 'NETWORK_TRANSMIT');

        /** Actions a client may run while the service is not Active. */
        const READ_ONLY = array('overview', 'activity');

        /** @var array */
        protected $params;

        /** @var string */
        protected $actor;

        /** @var \Cubepath\CubepathClient|null */
        protected $client;

        /** @var array|null Raw VPS from GET /vps/ */
        protected $vps;

        /**
         * @param array  $params WHMCS module parameters of the service
         * @param string $actor  client or admin
         */
        public function __construct(array $params, $actor)
        {
            $this->params = $params;
            $this->actor = $actor;
        }

        /**
         * Run an action and print its JSON response. Never returns.
         *
         * @param array  $params
         * @param string $actor
         * @param string $action
         * @param string $payload JSON object sent by the panel
         */
        public static function respond(array $params, $actor, $action, $payload)
        {
            // WHMCS runs htmlspecialchars() over $_POST, which breaks the JSON quotes.
            $data = json_decode(htmlspecialchars_decode((string)$payload, ENT_QUOTES), true);
            $controller = new self($params, $actor);

            try
            {
                $result = array('ok' => true, 'data' => $controller->handle((string)$action, is_array($data) ? $data : array()));
            }
            catch (PanelException $e)
            {
                $result = array('ok' => false, 'error' => $e->getMessage());
            }
            catch (\Cubepath\APIError $e)
            {
                $result = array('ok' => false, 'error' => self::apiMessage($e));
            }
            catch (\Exception $e)
            {
                logActivity('CubePath panel error (service #' . (int)$params['serviceid'] . ', ' . $action . '): ' . $e->getMessage());
                $result = array('ok' => false, 'error' => 'Unexpected error. Please try again or contact support.');
            }

            self::sendJson($result);
        }

        public static function sendJson(array $result)
        {
            while (ob_get_level() > 0)
            {
                ob_end_clean();
            }
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            echo json_encode($result);
            exit;
        }

        /**
         * Readable message for an API error; the API's own detail is shown
         * as is because it is written for end users.
         */
        protected static function apiMessage(\Cubepath\APIError $e)
        {
            $detail = trim($e->getDetail());
            // 403 is also used for business rules ("enable backups first"); only
            // credential and permission problems are hidden from the client.
            if ($e->isUnauthorized() || ($e->isForbidden() && ($detail === '' || preg_match('/token|scope|role|permission|not allowed/i', $detail))))
            {
                return 'The provider rejected the request. Please contact support.';
            }
            if ($e->isRateLimited())
            {
                return 'Too many requests. Please wait a moment and try again.';
            }
            if ($e->isServerError() || $detail === '')
            {
                return 'The provider could not complete the request. Please try again in a few minutes.';
            }

            return $detail;
        }

        /**
         * @param string $action
         * @param array  $data
         * @return mixed
         * @throws PanelException
         */
        public function handle($action, array $data)
        {
            $methods = array(
                'overview'         => 'overview',
                'credentials'      => 'credentials',
                'metrics'          => 'metrics',
                'power'            => 'power',
                'password'         => 'password',
                'label'            => 'label',
                'network'          => 'network',
                'rdns'             => 'rdns',
                'firewall'         => 'firewall',
                'firewall.save'    => 'saveFirewall',
                'backups'          => 'backups',
                'backups.create'   => 'createBackup',
                'backups.restore'  => 'restoreBackup',
                'backups.delete'   => 'deleteBackup',
                'backups.settings' => 'backupSettings',
                'backups.order'    => 'orderBackups',
                'isos'             => 'isos',
                'iso.mount'        => 'mountIso',
                'iso.unmount'      => 'unmountIso',
                'templates'        => 'templates',
                'reinstall'        => 'reinstall',
                'activity'         => 'activity',
            );

            if (!isset($methods[$action]))
            {
                throw new PanelException('Unknown action.');
            }

            if ($this->actor === self::ACTOR_CLIENT && !in_array($action, self::READ_ONLY, true)
                && $this->params['status'] !== 'Active')
            {
                throw new PanelException('This service is not active.');
            }

            return $this->{$methods[$action]}($data);
        }

        // ------------------------------------------------------------------
        // Overview
        // ------------------------------------------------------------------

        protected function overview()
        {
            $result = array('service' => $this->serviceInfo(), 'vps' => null, 'bandwidth' => null, 'notice' => null);

            if (!$this->vpsId())
            {
                return $result;
            }

            try
            {
                $raw = $this->rawVps();
            }
            catch (PanelException $e)
            {
                $result['notice'] = $e->getMessage();
                return $result;
            }

            $result['vps'] = CubepathHelper::normalizeVps($raw);

            if (in_array($raw['status'], array('active', 'stopped'), true))
            {
                try
                {
                    $usage = $this->graphql(
                        'query($id: ID!){ vps(id: $id){ bandwidthUsage{ inBytes outBytes totalBytes } } }',
                        array('id' => (string)$this->vpsId())
                    );
                    if (isset($usage['vps']['bandwidthUsage']))
                    {
                        $bw = $usage['vps']['bandwidthUsage'];
                        $result['bandwidth'] = array(
                            'inBytes'       => (float)$bw['inBytes'],
                            'outBytes'      => (float)$bw['outBytes'],
                            'totalBytes'    => (float)$bw['totalBytes'],
                            'includedBytes' => isset($raw['plan']['bandwidth']) ? (float)$raw['plan']['bandwidth'] * pow(1024, 4) : 0,
                        );
                    }
                }
                catch (\Exception $e)
                {
                    // Bandwidth is optional on the overview.
                }
            }

            return $result;
        }

        protected function serviceInfo()
        {
            $service = Capsule::table('tblhosting as h')
                ->join('tblproducts as p', 'p.id', '=', 'h.packageid')
                ->join('tblclients as c', 'c.id', '=', 'h.userid')
                ->where('h.id', (int)$this->params['serviceid'])
                ->first(array(
                    'h.id', 'h.domainstatus', 'h.domain', 'h.username', 'h.regdate', 'h.nextduedate', 'h.billingcycle',
                    'h.amount', 'p.name as product', 'c.id as clientid', 'c.firstname', 'c.lastname', 'c.companyname',
                    'c.email', 'c.currency',
                ));

            $amount = (string)$service->amount;
            try
            {
                $amount = (string)formatCurrency($service->amount, $service->currency);
            }
            catch (\Exception $e)
            {
                // Keep the raw amount.
            }

            $info = array(
                'id'           => (int)$service->id,
                'status'       => $service->domainstatus,
                'product'      => $service->product,
                'domain'       => (string)$service->domain,
                'username'     => (string)$service->username,
                'regdate'      => $service->regdate,
                'nextduedate'  => $service->nextduedate,
                'billingcycle' => $service->billingcycle,
                'amount'       => $amount,
            );

            if ($this->actor === self::ACTOR_ADMIN)
            {
                $info['client'] = array(
                    'id'    => (int)$service->clientid,
                    'name'  => trim($service->firstname . ' ' . $service->lastname) ?: $service->companyname,
                    'email' => $service->email,
                );
            }

            return $info;
        }

        protected function credentials()
        {
            $password = Capsule::table('tblhosting')->where('id', (int)$this->params['serviceid'])->value('password');

            return array('password' => $password ? (string)decrypt($password) : '');
        }

        // ------------------------------------------------------------------
        // Metrics
        // ------------------------------------------------------------------

        protected function metrics(array $data)
        {
            $range = isset($data['range']) ? (string)$data['range'] : 'H1';
            if (!in_array($range, self::METRIC_RANGES, true))
            {
                throw new PanelException('Invalid time range.');
            }

            $metrics = self::METRICS;
            if (isset($data['metrics']) && is_array($data['metrics']))
            {
                $metrics = array_values(array_intersect(self::METRICS, $data['metrics']));
            }

            $response = $this->client()->post('/graphql', array(
                'query'     => 'query($id: ID!, $range: TimeRange!, $metrics: [VPSMetric!]){ vps(id: $id){ metrics(range: $range, metrics: $metrics){ step series{ name unit points{ ts value } } } } }',
                'variables' => array('id' => (string)$this->requireVpsId(), 'range' => $range, 'metrics' => $metrics),
            ));

            $unavailable = false;
            foreach (isset($response['errors']) ? $response['errors'] : array() as $error)
            {
                $code = isset($error['extensions']['code']) ? $error['extensions']['code'] : '';
                if ($code === 'METRICS_UNAVAILABLE')
                {
                    $unavailable = true;
                    continue;
                }
                if ($code === 'FORBIDDEN')
                {
                    return array('step' => 0, 'series' => array(), 'unavailable' => true);
                }
                throw new PanelException(isset($error['message']) ? $error['message'] : 'Metrics are not available.');
            }

            $result = isset($response['data']['vps']['metrics']) ? $response['data']['vps']['metrics'] : array('step' => 0, 'series' => array());

            $series = array();
            foreach ($result['series'] as $item)
            {
                $points = array();
                foreach ($item['points'] as $point)
                {
                    $points[] = array((int)$point['ts'], (float)$point['value']);
                }
                $series[] = array('name' => $item['name'], 'unit' => $item['unit'], 'points' => $points);
            }

            return array('step' => (int)$result['step'], 'series' => $series, 'unavailable' => $unavailable);
        }

        // ------------------------------------------------------------------
        // Power, password, label
        // ------------------------------------------------------------------

        protected function power(array $data)
        {
            $action = isset($data['action']) ? (string)$data['action'] : '';
            if (!isset(CubepathHelper::POWER_ACTIONS[$action]))
            {
                throw new PanelException('Unknown power action.');
            }

            return $this->logged('power_' . $action, null, function () use ($action) {
                CubepathHelper::power($this->client(), $this->requireVpsId(), $action);
                return array();
            });
        }

        protected function password(array $data)
        {
            $password = isset($data['password']) ? (string)$data['password'] : '';
            self::validatePassword($password);

            return $this->logged('password', null, function () use ($password) {
                $this->client()->vps()->changePassword($this->requireVpsId(), $password);
                Capsule::table('tblhosting')->where('id', (int)$this->params['serviceid'])->update(array('password' => encrypt($password)));
                return array();
            });
        }

        protected function label(array $data)
        {
            $label = isset($data['label']) ? trim((string)$data['label']) : '';
            if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,62}$/', $label))
            {
                throw new PanelException('Use letters, numbers, dots, hyphens and underscores (up to 63 characters).');
            }

            return $this->logged('label', $label, function () use ($label) {
                $this->client()->vps()->update($this->requireVpsId(), array('label' => $label));
                return array();
            });
        }

        // ------------------------------------------------------------------
        // Network
        // ------------------------------------------------------------------

        protected function network()
        {
            $raw = $this->rawVps();
            $vpsId = (string)$this->requireVpsId();

            $rdns = array();
            try
            {
                $list = $this->client()->floatingIPs()->list();
                foreach (isset($list['single_ips']) ? $list['single_ips'] : array() as $ip)
                {
                    if (isset($ip['service_type'], $ip['service_id']) && $ip['service_type'] === 'vps' && (string)$ip['service_id'] === $vpsId)
                    {
                        $rdns[$ip['address']] = isset($ip['reverse_dns']) ? $ip['reverse_dns'] : null;
                    }
                }
            }
            catch (\Cubepath\APIError $e)
            {
                // Without the floating IP scope the addresses are still listed.
            }

            $ips = array();
            foreach ($this->vpsIps($raw) as $ip)
            {
                $ip['reverse_dns'] = isset($rdns[$ip['address']]) ? $rdns[$ip['address']] : null;
                $ips[] = $ip;
            }

            $private = null;
            if (!empty($raw['network']['assigned_ip']))
            {
                $private = array(
                    'name'        => (string)($raw['network']['label'] ?: $raw['network']['name']),
                    'ip_range'    => (string)$raw['network']['ip_range'],
                    'prefix'      => (int)$raw['network']['prefix'],
                    'assigned_ip' => (string)$raw['network']['assigned_ip'],
                );
            }

            return array('ips' => $ips, 'private' => $private);
        }

        protected function rdns(array $data)
        {
            $ip = isset($data['ip']) ? (string)$data['ip'] : '';
            $value = isset($data['value']) ? strtolower(trim((string)$data['value'])) : '';

            $owned = false;
            foreach ($this->vpsIps($this->rawVps()) as $address)
            {
                $owned = $owned || $address['address'] === $ip;
            }
            if (!$owned)
            {
                throw new PanelException('This IP address does not belong to the server.');
            }
            if ($value !== '' && !preg_match('/^(?=.{1,253}\.?$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}\.?$/', $value))
            {
                throw new PanelException('Enter a valid hostname, for example server.example.com.');
            }

            return $this->logged('rdns', $ip . ' → ' . ($value !== '' ? $value : '(removed)'), function () use ($ip, $value) {
                $this->client()->post('/floating_ips/reverse_dns/configure?' . http_build_query(array('ip' => $ip, 'reverse_dns' => $value)));
                return array();
            });
        }

        /**
         * @return array<int, array{address: string, type: string, netmask: string, is_primary: bool}>
         */
        protected function vpsIps(array $raw)
        {
            $ips = array();
            $list = isset($raw['floating_ips']['list']) && is_array($raw['floating_ips']['list']) ? $raw['floating_ips']['list'] : array();
            foreach ($list as $ip)
            {
                if (empty($ip['address']))
                {
                    continue;
                }
                $ips[] = array(
                    'address'    => (string)$ip['address'],
                    'type'       => isset($ip['type']) ? (string)$ip['type'] : (strpos($ip['address'], ':') !== false ? 'IPv6' : 'IPv4'),
                    'netmask'    => isset($ip['netmask']) ? (string)$ip['netmask'] : '',
                    'is_primary' => !empty($ip['is_primary']),
                );
            }

            return $ips;
        }

        // ------------------------------------------------------------------
        // Firewall: one group per service, created on first save
        // ------------------------------------------------------------------

        protected function firewall()
        {
            $raw = $this->rawVps();
            $ownId = $this->firewallGroupId();
            $attached = isset($raw['firewall_groups']) && is_array($raw['firewall_groups']) ? $raw['firewall_groups'] : array();

            $rules = array();
            $enabled = false;
            if ($ownId)
            {
                $group = $this->findFirewallGroup($ownId);
                if ($group)
                {
                    $rules = isset($group['rules']) && is_array($group['rules']) ? $group['rules'] : array();
                }
                foreach ($attached as $g)
                {
                    $enabled = $enabled || (int)$g['id'] === $ownId;
                }
                $enabled = $enabled && !empty($raw['firewall_enabled']);
            }

            $external = array();
            if ($this->actor === self::ACTOR_ADMIN)
            {
                foreach ($attached as $g)
                {
                    if ((int)$g['id'] !== $ownId)
                    {
                        $external[] = array('id' => (int)$g['id'], 'name' => (string)$g['name']);
                    }
                }
            }

            return array(
                'enabled'  => $enabled,
                'rules'    => array_map(array($this, 'normalizeRule'), $rules),
                'external' => $external,
                'maxRules' => self::MAX_FIREWALL_RULES,
            );
        }

        protected function saveFirewall(array $data)
        {
            $enabled = !empty($data['enabled']);
            $input = isset($data['rules']) && is_array($data['rules']) ? $data['rules'] : array();
            if (count($input) > self::MAX_FIREWALL_RULES)
            {
                throw new PanelException('A firewall can have at most ' . self::MAX_FIREWALL_RULES . ' rules.');
            }
            $rules = array();
            foreach ($input as $rule)
            {
                $rules[] = self::validateRule(is_array($rule) ? $rule : array());
            }

            $details = ($enabled ? 'enabled' : 'disabled') . ', ' . count($rules) . ' rules';

            return $this->logged('firewall', $details, function () use ($enabled, $rules) {
                $raw = $this->rawVps();
                $vpsId = $this->requireVpsId();
                $groupId = $this->firewallGroupId();

                if ($groupId && $this->findFirewallGroup($groupId))
                {
                    $this->client()->firewall()->update($groupId, array('rules' => $rules, 'enabled' => true));
                }
                else
                {
                    $groupId = $this->createFirewallGroup($raw, $rules);
                }

                $attached = array();
                foreach (isset($raw['firewall_groups']) && is_array($raw['firewall_groups']) ? $raw['firewall_groups'] : array() as $g)
                {
                    $attached[] = (int)$g['id'];
                }
                $others = array_values(array_diff($attached, array($groupId)));
                $wanted = $enabled ? array_merge($others, array($groupId)) : $others;

                if ($wanted !== $attached || (bool)$raw['firewall_enabled'] !== (count($wanted) > 0))
                {
                    $this->client()->firewall()->assignToVPS($vpsId, $wanted);
                }

                return array();
            });
        }

        protected function createFirewallGroup(array $raw, array $rules)
        {
            if (empty($raw['project']['id']))
            {
                throw new PanelException('The server has no project; the firewall cannot be created.');
            }

            $serviceId = (int)$this->params['serviceid'];
            $name = 'whmcs-service-' . $serviceId;
            try
            {
                $group = $this->client()->firewall()->create(array(
                    'project_id' => (int)$raw['project']['id'],
                    'name'       => $name,
                    'rules'      => $rules,
                    'enabled'    => true,
                ));
            }
            catch (\Cubepath\APIError $e)
            {
                if (!$e->isBadRequest())
                {
                    throw $e;
                }
                // A group with this name is left over from an earlier service row.
                $group = $this->client()->firewall()->create(array(
                    'project_id' => (int)$raw['project']['id'],
                    'name'       => $name . '-' . time(),
                    'rules'      => $rules,
                    'enabled'    => true,
                ));
            }

            if (empty($group['id']))
            {
                throw new PanelException('The firewall could not be created.');
            }

            self::ensureFirewallTable();
            Capsule::table(self::FIREWALL_TABLE)->where('service_id', $serviceId)->delete();
            Capsule::table(self::FIREWALL_TABLE)->insert(array(
                'service_id' => $serviceId,
                'group_id'   => (int)$group['id'],
                'created_at' => gmdate('Y-m-d H:i:s'),
            ));

            return (int)$group['id'];
        }

        protected function firewallGroupId()
        {
            self::ensureFirewallTable();

            return (int)Capsule::table(self::FIREWALL_TABLE)->where('service_id', (int)$this->params['serviceid'])->value('group_id');
        }

        protected function findFirewallGroup($groupId)
        {
            foreach ($this->client()->firewall()->list() as $group)
            {
                if ((int)$group['id'] === (int)$groupId)
                {
                    return $group;
                }
            }

            return null;
        }

        /**
         * Delete the service's firewall group, if any. Called on termination.
         *
         * @param \Cubepath\CubepathClient $client
         * @param int                       $serviceId
         */
        public static function deleteFirewallGroup($client, $serviceId)
        {
            self::ensureFirewallTable();
            $groupId = (int)Capsule::table(self::FIREWALL_TABLE)->where('service_id', (int)$serviceId)->value('group_id');
            if (!$groupId)
            {
                return;
            }

            try
            {
                $client->firewall()->delete($groupId);
            }
            catch (\Cubepath\APIError $e)
            {
                if (!$e->isNotFound())
                {
                    logActivity('CubePath: could not delete firewall group #' . $groupId . ' of service #' . (int)$serviceId . ': ' . $e->getMessage());
                    return;
                }
            }
            Capsule::table(self::FIREWALL_TABLE)->where('service_id', (int)$serviceId)->delete();
        }

        public static function ensureFirewallTable()
        {
            static $checked = false;
            if ($checked)
            {
                return;
            }
            $checked = true;

            if (!Capsule::schema()->hasTable(self::FIREWALL_TABLE))
            {
                Capsule::schema()->create(self::FIREWALL_TABLE, function ($table) {
                    $table->integer('service_id')->primary();
                    $table->integer('group_id');
                    $table->dateTime('created_at');
                });
            }
        }

        protected function normalizeRule($rule)
        {
            return array(
                'direction' => isset($rule['direction']) ? $rule['direction'] : 'in',
                'protocol'  => isset($rule['protocol']) ? $rule['protocol'] : 'tcp',
                'port'      => isset($rule['port']) && $rule['port'] !== '' ? (string)$rule['port'] : null,
                'source'    => isset($rule['source']) && $rule['source'] !== '' ? (string)$rule['source'] : null,
                'comment'   => isset($rule['comment']) && $rule['comment'] !== '' ? (string)$rule['comment'] : null,
            );
        }

        protected static function validateRule(array $rule)
        {
            $direction = isset($rule['direction']) ? $rule['direction'] : '';
            $protocol = isset($rule['protocol']) ? $rule['protocol'] : '';
            if (!in_array($direction, array('in', 'out'), true) || !in_array($protocol, array('tcp', 'udp', 'icmp', 'gre'), true))
            {
                throw new PanelException('Invalid firewall rule.');
            }

            $port = null;
            if (($protocol === 'tcp' || $protocol === 'udp') && isset($rule['port']) && trim((string)$rule['port']) !== '')
            {
                $port = preg_replace('/\s+/', '', (string)$rule['port']);
                $items = explode(',', $port);
                if (count($items) > 20)
                {
                    throw new PanelException('A rule can list at most 20 ports or ranges.');
                }
                foreach ($items as $item)
                {
                    if (!preg_match('/^(\d{1,5})(?:-(\d{1,5}))?$/', $item, $m)
                        || (int)$m[1] < 1 || (int)$m[1] > 65535
                        || (isset($m[2]) && ((int)$m[2] > 65535 || (int)$m[2] <= (int)$m[1])))
                    {
                        throw new PanelException('Invalid port: ' . $item . '. Use 80, 80-443 or 80,443.');
                    }
                }
            }

            $source = null;
            if (isset($rule['source']) && trim((string)$rule['source']) !== '')
            {
                $source = trim((string)$rule['source']);
                $parts = explode('/', $source, 2);
                $isV6 = filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
                if (filter_var($parts[0], FILTER_VALIDATE_IP) === false
                    || (isset($parts[1]) && (!ctype_digit($parts[1]) || (int)$parts[1] > ($isV6 ? 128 : 32))))
                {
                    throw new PanelException('Invalid source: ' . $source . '. Use an IP or CIDR such as 203.0.113.0/24.');
                }
            }

            $comment = isset($rule['comment']) && trim((string)$rule['comment']) !== '' ? mb_substr(trim((string)$rule['comment']), 0, 100) : null;

            return array('direction' => $direction, 'protocol' => $protocol, 'port' => $port, 'source' => $source, 'comment' => $comment);
        }

        // ------------------------------------------------------------------
        // Backups
        // ------------------------------------------------------------------

        protected function backups()
        {
            $vpsId = $this->requireVpsId();
            $list = $this->client()->vps()->backups()->list($vpsId, 50, 0);

            $settings = !empty($list['settings']) ? $list['settings'] : $this->client()->vps()->backups()->getSettings($vpsId);

            $backups = array();
            foreach (isset($list['backups']) ? $list['backups'] : array() as $b)
            {
                $backups[] = array(
                    'id'            => (int)$b['id'],
                    'backup_type'   => (string)$b['backup_type'],
                    'status'        => (string)$b['status'],
                    'progress'      => (int)$b['progress'],
                    'size_gb'       => isset($b['size_gb']) ? (float)$b['size_gb'] : null,
                    'notes'         => isset($b['notes']) ? $b['notes'] : null,
                    'error_message' => $this->actor === self::ACTOR_ADMIN && isset($b['error_message']) ? $b['error_message'] : null,
                    'created_at'    => self::isoDate($b['created_at']),
                    'completed_at'  => isset($b['completed_at']) ? self::isoDate($b['completed_at']) : null,
                );
            }

            return array(
                'backups'  => $backups,
                'total'    => isset($list['total']) ? (int)$list['total'] : count($backups),
                'locked'   => $this->backupsLocked(),
                'settings' => array(
                    'enabled'        => !empty($settings['enabled']),
                    'schedule_hour'  => isset($settings['schedule_hour']) ? (int)$settings['schedule_hour'] : 3,
                    'retention_days' => isset($settings['retention_days']) ? (int)$settings['retention_days'] : 7,
                    'max_backups'    => isset($settings['max_backups']) ? (int)$settings['max_backups'] : 7,
                ),
            );
        }

        protected function createBackup(array $data)
        {
            $notes = isset($data['notes']) ? mb_substr(trim((string)$data['notes']), 0, 200) : '';

            return $this->logged('backup_create', $notes !== '' ? $notes : null, function () use ($notes) {
                $this->client()->vps()->backups()->create($this->requireVpsId(), $notes !== '' ? $notes : null);
                return array();
            });
        }

        protected function restoreBackup(array $data)
        {
            $backupId = $this->ownedBackupId($data);

            return $this->logged('backup_restore', '#' . $backupId, function () use ($backupId) {
                $this->client()->vps()->backups()->restore($this->requireVpsId(), $backupId);
                return array();
            });
        }

        protected function deleteBackup(array $data)
        {
            $backupId = $this->ownedBackupId($data);

            return $this->logged('backup_delete', '#' . $backupId, function () use ($backupId) {
                $this->client()->vps()->backups()->delete($this->requireVpsId(), $backupId);
                return array();
            });
        }

        protected function ownedBackupId(array $data)
        {
            $backupId = isset($data['id']) ? (int)$data['id'] : 0;
            $list = $this->client()->vps()->backups()->list($this->requireVpsId(), 50, 0);
            foreach (isset($list['backups']) ? $list['backups'] : array() as $b)
            {
                if ((int)$b['id'] === $backupId)
                {
                    return $backupId;
                }
            }

            throw new PanelException('Backup not found.');
        }

        /**
         * Whether the client may not turn automatic backups on because the
         * product sells them and the service has not bought them.
         */
        protected function backupsLocked()
        {
            return $this->actor === self::ACTOR_CLIENT && CubepathHelper::backupsPurchased((int)$this->params['serviceid']) === false;
        }

        /**
         * Order the backups option for this service alone. WHMCS's upgrade
         * page would also let the client change the location, image and
         * network, which only take effect on create, so the products leave
         * it off and this places the upgrade order instead. Once its invoice
         * is paid WHMCS runs ChangePackage, which turns backups on.
         *
         * @return array invoiceUrl: the invoice to pay, or null when nothing is due
         */
        protected function orderBackups()
        {
            if (!$this->backupsLocked())
            {
                throw new PanelException('Automatic backups are already included in this service.');
            }

            $serviceId = (int)$this->params['serviceid'];

            // An order placed before and not paid yet: send the client to its invoice.
            $pending = Capsule::table('tblupgrades as u')
                ->join('tblorders as o', 'o.id', '=', 'u.orderid')
                ->join('tblinvoices as i', 'i.id', '=', 'o.invoiceid')
                ->where('u.relid', $serviceId)
                ->where('u.type', 'configoptions')
                ->where('u.paid', 'N')
                ->where('i.status', 'Unpaid')
                ->value('i.id');
            if ($pending)
            {
                return array('invoiceUrl' => $this->invoiceUrl($pending));
            }

            $optionId = Capsule::table('tblhosting as h')
                ->join('tblproductconfiglinks as l', 'l.pid', '=', 'h.packageid')
                ->join('tblproductconfigoptions as o', 'o.gid', '=', 'l.gid')
                ->where('h.id', $serviceId)
                ->where('o.optionname', 'LIKE', 'backups|%')
                ->value('o.id');
            if (!$optionId)
            {
                throw new PanelException('Automatic backups are not sold for this product.');
            }

            $result = localAPI('UpgradeProduct', array(
                'serviceid'     => $serviceId,
                'type'          => 'configoptions',
                'configoptions' => array((int)$optionId => 1),
                'paymentmethod' => (string)Capsule::table('tblhosting')->where('id', $serviceId)->value('paymentmethod'),
            ));
            if (!isset($result['result']) || $result['result'] !== 'success')
            {
                ActivityHelper::log($serviceId, $this->actor, 'backup_order', false, isset($result['message']) ? $result['message'] : null);
                throw new PanelException('The backups could not be ordered. Please contact support.');
            }
            ActivityHelper::log($serviceId, $this->actor, 'backup_order', true, 'order #' . (int)$result['orderid']);

            // Nothing due (credit, or a free option): WHMCS has already applied it.
            return array('invoiceUrl' => !empty($result['invoiceid']) ? $this->invoiceUrl($result['invoiceid']) : null);
        }

        protected function invoiceUrl($invoiceId)
        {
            return CubepathHelper::systemUrl() . 'viewinvoice.php?id=' . (int)$invoiceId;
        }

        protected function backupSettings(array $data)
        {
            if (!empty($data['enabled']) && $this->backupsLocked())
            {
                throw new PanelException('Automatic backups are not included in this service. Add them first.');
            }

            $settings = array(
                'enabled'        => !empty($data['enabled']),
                'schedule_hour'  => isset($data['schedule_hour']) ? (int)$data['schedule_hour'] : 3,
                'retention_days' => isset($data['retention_days']) ? (int)$data['retention_days'] : 7,
                'max_backups'    => isset($data['max_backups']) ? (int)$data['max_backups'] : 7,
            );
            if ($settings['schedule_hour'] < 0 || $settings['schedule_hour'] > 23
                || $settings['retention_days'] < 1 || $settings['retention_days'] > 7
                || $settings['max_backups'] < 1 || $settings['max_backups'] > 10)
            {
                throw new PanelException('Invalid backup settings.');
            }

            $details = $settings['enabled']
                ? sprintf('daily at %02d:00 UTC, %d days, max %d', $settings['schedule_hour'], $settings['retention_days'], $settings['max_backups'])
                : 'disabled';

            return $this->logged('backup_settings', $details, function () use ($settings) {
                $this->client()->vps()->backups()->updateSettings($this->requireVpsId(), $settings);
                return array();
            });
        }

        // ------------------------------------------------------------------
        // ISOs
        // ------------------------------------------------------------------

        protected function isos()
        {
            $list = $this->client()->vps()->isos()->list($this->requireVpsId());

            $items = array();
            foreach (isset($list['items']) ? $list['items'] : array() as $iso)
            {
                $items[] = array(
                    'id'          => (string)$iso['id'],
                    'name'        => (string)$iso['name'],
                    'description' => isset($iso['description']) ? $iso['description'] : null,
                    'file_size'   => (int)$iso['file_size'],
                    'is_mounted'  => !empty($iso['is_mounted']),
                );
            }

            return array('mounted' => isset($list['mounted_iso_id']) ? $list['mounted_iso_id'] : null, 'items' => $items);
        }

        protected function mountIso(array $data)
        {
            $isoId = isset($data['id']) ? (string)$data['id'] : '';
            $name = null;
            foreach ($this->isos()['items'] as $iso)
            {
                if ($iso['id'] === $isoId)
                {
                    $name = $iso['name'];
                }
            }
            if ($name === null)
            {
                throw new PanelException('ISO not found.');
            }

            return $this->logged('iso_mount', $name, function () use ($isoId) {
                $this->client()->vps()->isos()->mount($this->requireVpsId(), $isoId);
                return array();
            });
        }

        protected function unmountIso()
        {
            return $this->logged('iso_unmount', null, function () {
                $this->client()->vps()->isos()->unmount($this->requireVpsId());
                return array();
            });
        }

        // ------------------------------------------------------------------
        // Reinstall
        // ------------------------------------------------------------------

        /**
         * Operating systems offered for reinstalling: the ones the product
         * sells in its "template" configurable option, or the full catalog
         * for products without that option.
         *
         * @return array<int, array{value: string, label: string}>
         */
        protected function templates()
        {
            $options = Capsule::table('tblproductconfiglinks as l')
                ->join('tblproductconfigoptions as o', 'o.gid', '=', 'l.gid')
                ->join('tblproductconfigoptionssub as s', 's.configid', '=', 'o.id')
                ->where('l.pid', (int)$this->params['pid'])
                ->where('o.optionname', 'like', 'template|%')
                ->where('s.hidden', 0)
                ->orderBy('s.sortorder')
                ->orderBy('s.id')
                ->pluck('s.optionname')
                ->all();

            $templates = array();
            foreach ($options as $option)
            {
                $parts = explode('|', $option, 2);
                $value = trim($parts[0]);
                if ($value !== '')
                {
                    $templates[] = array('value' => $value, 'label' => trim(isset($parts[1]) ? $parts[1] : $parts[0]));
                }
            }

            if ($templates)
            {
                return $templates;
            }

            $catalog = $this->client()->vps()->templates();
            foreach (isset($catalog['operating_systems']) ? $catalog['operating_systems'] : array() as $os)
            {
                $templates[] = array('value' => (string)$os['template_name'], 'label' => (string)$os['os_name']);
            }

            return $templates;
        }

        protected function reinstall(array $data)
        {
            $template = isset($data['template']) ? (string)$data['template'] : '';
            $password = isset($data['password']) ? (string)$data['password'] : '';
            self::validatePassword($password);

            $label = null;
            foreach ($this->templates() as $option)
            {
                if ($option['value'] === $template)
                {
                    $label = $option['label'];
                }
            }
            if ($label === null)
            {
                throw new PanelException('This operating system is not available.');
            }

            return $this->logged('reinstall', $label, function () use ($template, $password) {
                $this->client()->post('/vps/reinstall/' . $this->requireVpsId(), array(
                    'template_name' => $template,
                    'password'      => $password,
                ));
                Capsule::table('tblhosting')->where('id', (int)$this->params['serviceid'])->update(array('password' => encrypt($password)));
                CubepathHelper::setConfigurableOptionValue((int)$this->params['serviceid'], 'template', $template);
                return array();
            });
        }

        // ------------------------------------------------------------------
        // Activity
        // ------------------------------------------------------------------

        protected function activity()
        {
            return ActivityHelper::recent((int)$this->params['serviceid']);
        }

        // ------------------------------------------------------------------
        // Helpers
        // ------------------------------------------------------------------

        /**
         * Run a change and record it in the activity log, successful or not.
         */
        protected function logged($action, $details, callable $fn)
        {
            $serviceId = (int)$this->params['serviceid'];
            try
            {
                $result = $fn();
            }
            catch (\Cubepath\APIError $e)
            {
                ActivityHelper::log($serviceId, $this->actor, $action, false, trim(($details ? $details . ': ' : '') . self::apiMessage($e)));
                throw $e;
            }
            catch (PanelException $e)
            {
                ActivityHelper::log($serviceId, $this->actor, $action, false, trim(($details ? $details . ': ' : '') . $e->getMessage()));
                throw $e;
            }
            ActivityHelper::log($serviceId, $this->actor, $action, true, $details);

            return $result;
        }

        protected function client()
        {
            if ($this->client === null)
            {
                try
                {
                    $this->client = CubepathHelper::client($this->params);
                }
                catch (\RuntimeException $e)
                {
                    throw new PanelException($this->actor === self::ACTOR_ADMIN ? $e->getMessage() : 'This service is not configured correctly. Please contact support.');
                }
            }

            return $this->client;
        }

        protected function vpsId()
        {
            $id = isset($this->params['customfields']['vps_id']) ? $this->params['customfields']['vps_id'] : '';
            if ($id === '' || $id === null)
            {
                $id = CubepathHelper::getCustomFieldValue($this->params['serviceid'], 'vps_id');
            }

            return ctype_digit((string)$id) ? (int)$id : 0;
        }

        protected function requireVpsId()
        {
            $id = $this->vpsId();
            if (!$id)
            {
                throw new PanelException('This server has not been created yet.');
            }

            return $id;
        }

        protected function rawVps()
        {
            if ($this->vps === null)
            {
                $this->vps = CubepathHelper::findVps($this->client(), $this->requireVpsId());
                if ($this->vps === null)
                {
                    throw new PanelException('The server was not found at the provider. Please contact support.');
                }
            }

            return $this->vps;
        }

        protected function graphql($query, array $variables)
        {
            return $this->client()->graphql($query, $variables);
        }

        protected static function validatePassword($password)
        {
            if (strlen($password) < 8 || strlen($password) > 128)
            {
                throw new PanelException('The password must have between 8 and 128 characters.');
            }
            if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password))
            {
                throw new PanelException('The password must contain letters and numbers.');
            }
            if (preg_match('/[\x00-\x1F\x7F]/', $password))
            {
                throw new PanelException('The password contains invalid characters.');
            }
        }

        protected static function isoDate($value)
        {
            if (!$value)
            {
                return null;
            }
            $ts = strtotime(preg_match('/(Z|[+-]\d{2}:?\d{2})$/', $value) ? $value : $value . ' UTC');

            return $ts ? gmdate('Y-m-d\TH:i:s\Z', $ts) : $value;
        }
    }

    class PanelException extends \Exception
    {
    }
}
