<?php
/**
 * CubePath Cloud WHMCS Module - Provisioning Class
 *
 * Handles all server provisioning operations: create, suspend, unsuspend,
 * terminate, resize, and power management via the CubePath PHP SDK.
 */

use Illuminate\Database\Capsule\Manager as Capsule;

class Cubepath
{
    /** @var array WHMCS module parameters */
    protected $params;

    /** @var \Cubepath\CubepathClient|null Cached API client instance */
    protected $client;

    /**
     * @param array $params WHMCS module parameters
     */
    public function __construct($params)
    {
        $this->params = $params;
    }

    /**
     * Create and return a CubePath API client using the service's API token.
     *
     * @return \Cubepath\CubepathClient
     * @throws \RuntimeException If no API token is configured
     */
    public function getCubepathClient()
    {
        if ($this->client === null)
        {
            $this->client = CubepathHelper::client($this->params);
        }
        return $this->client;
    }

    /**
     * Provision a new VPS instance on CubePath Cloud.
     *
     * Reads plan, project, location, template, and hostname from WHMCS params,
     * creates the VPS via the API, and stores the VPS ID and IP address
     * in custom fields for future operations.
     *
     * @return string 'success' or error message
     */
    public function createAccount()
    {
        try
        {
            $client = $this->getCubepathClient();

            // Get the plan name from product config
            $planName = $this->params['configoption2'];
            if (empty($planName))
            {
                return 'Plan name is not configured (configoption2)';
            }

            // Get project ID: prefer custom field, fall back to product config
            $projectId = CubepathHelper::getCustomFieldValue($this->params['serviceid'], 'project_id');
            if (empty($projectId))
            {
                $projectId = $this->params['configoption3'];
            }
            if (empty($projectId))
            {
                return 'Project ID is not configured';
            }
            $projectId = (int)$projectId;

            // Get location from configurable options
            $location = CubepathHelper::getConfigurableOptionValue($this->params['serviceid'], 'location');
            if (empty($location))
            {
                return 'Location is not configured';
            }

            // Get OS template from configurable options
            $template = CubepathHelper::getConfigurableOptionValue($this->params['serviceid'], 'template');
            if (empty($template))
            {
                return 'OS template is not configured';
            }

            $hostname = $this->hostname();

            // The API requires a root password or an SSH key; use the service password WHMCS generated
            $password = $this->servicePassword();

            $request = array(
                'name'          => $hostname,
                'label'         => $hostname,
                'plan_name'     => $planName,
                'template_name' => $template,
                'location_name' => $location,
                'password'      => $password,
            );

            if ((int)CubepathHelper::getConfigurableOptionQty($this->params['serviceid'], 'backups') > 0)
            {
                $request['enable_backups'] = true;
            }
            if (CubepathHelper::getConfigurableOptionValue($this->params['serviceid'], 'network') === \CubePath\WHMCS\Addon\Products::NETWORK_IPV6_ONLY)
            {
                $request['ipv4'] = false;
            }

            // Windows images take neither SSH keys nor cloud-init.
            $windows = stripos($template, 'windows') === 0;
            // WHMCS stores order form fields HTML-encoded, which would break YAML quotes.
            $cloudInit = trim(html_entity_decode((string)CubepathHelper::getCustomFieldValue($this->params['serviceid'], 'cloud_init'), ENT_QUOTES, 'UTF-8'));
            if ($cloudInit !== '' && !$windows)
            {
                $request['custom_cloudinit'] = $cloudInit;
            }

            $createdKeyId = null;
            $sshKey = trim(html_entity_decode((string)CubepathHelper::getCustomFieldValue($this->params['serviceid'], 'ssh_key'), ENT_QUOTES, 'UTF-8'));
            if ($sshKey !== '' && !$windows)
            {
                list($keyId, $created) = $this->sshKeyId($client, $sshKey);
                $request['ssh_key_ids'] = array($keyId);
                $createdKeyId = $created ? $keyId : null;
            }

            try
            {
                $result = $client->vps()->create($projectId, $request);
            }
            catch (\Exception $e)
            {
                if ($createdKeyId)
                {
                    CubepathHelper::deleteSshKey($client, $createdKeyId);
                }
                throw $e;
            }

            // Keys created for the service are deleted with it.
            if ($createdKeyId)
            {
                \CubePath\WHMCS\Addon\Products::ensureCustomFields((int)$this->params['pid']);
                CubepathHelper::setCustomFieldValue($this->params['serviceid'], 'ssh_key_id', $createdKeyId);
            }

            if (empty($result['vps_id']))
            {
                return 'CubePath API did not return a VPS ID';
            }

            // Store the VPS and project IDs in custom fields for future operations
            CubepathHelper::setCustomFieldValue($this->params['serviceid'], 'vps_id', $result['vps_id']);
            CubepathHelper::setCustomFieldValue($this->params['serviceid'], 'project_id', $projectId);

            $hosting = array('domain' => $hostname, 'username' => 'root');
            if (!empty($result['ipv4_address']))
            {
                CubepathHelper::setCustomFieldValue($this->params['serviceid'], 'ip_address', $result['ipv4_address']);
                $hosting['dedicatedip'] = $result['ipv4_address'];
            }
            if (!empty($result['ipv6_address']))
            {
                $hosting['assignedips'] = $result['ipv6_address'];
            }
            Capsule::table('tblhosting')->where('id', $this->params['serviceid'])->update($hosting);

            return 'success';
        }
        catch (\Cubepath\APIError $e)
        {
            return 'CubePath API Error: ' . $e->getMessage();
        }
        catch (\Exception $e)
        {
            return 'Error creating account: ' . $e->getMessage();
        }
    }

    /**
     * Suspend a VPS by powering it off.
     *
     * @return string 'success' or error message
     */
    public function suspendAccount()
    {
        try
        {
            $client = $this->getCubepathClient();
            $vpsId = $this->getVpsId();

            if (empty($vpsId))
            {
                return 'The VPS ID of this service is not set';
            }

            CubepathHelper::power($client, $vpsId, 'stop');
            return 'success';
        }
        catch (\Cubepath\APIError $e)
        {
            return 'CubePath API Error: ' . $e->getMessage();
        }
        catch (\Exception $e)
        {
            return 'Error suspending account: ' . $e->getMessage();
        }
    }

    /**
     * Unsuspend a VPS by powering it on.
     *
     * @return string 'success' or error message
     */
    public function unsuspendAccount()
    {
        try
        {
            $client = $this->getCubepathClient();
            $vpsId = $this->getVpsId();

            if (empty($vpsId))
            {
                return 'The VPS ID of this service is not set';
            }

            CubepathHelper::power($client, $vpsId, 'start');
            return 'success';
        }
        catch (\Cubepath\APIError $e)
        {
            return 'CubePath API Error: ' . $e->getMessage();
        }
        catch (\Exception $e)
        {
            return 'Error unsuspending account: ' . $e->getMessage();
        }
    }

    /**
     * Terminate (permanently destroy) a VPS.
     *
     * @return string 'success' or error message
     */
    public function terminateAccount()
    {
        try
        {
            $client = $this->getCubepathClient();
            $vpsId = $this->getVpsId();

            if (empty($vpsId))
            {
                return 'The VPS ID of this service is not set';
            }

            $client->vps()->destroy((int)$vpsId);

            // The key stays in use until the VPS is gone; the daily cron retries what fails here.
            $keyId = (int)CubepathHelper::getCustomFieldValue($this->params['serviceid'], 'ssh_key_id');
            if ($keyId && CubepathHelper::deleteSshKey($client, $keyId))
            {
                CubepathHelper::setCustomFieldValue($this->params['serviceid'], 'ssh_key_id', '');
            }

            return 'success';
        }
        catch (\Cubepath\APIError $e)
        {
            return 'CubePath API Error: ' . $e->getMessage();
        }
        catch (\Exception $e)
        {
            return 'Error terminating account: ' . $e->getMessage();
        }
    }

    /**
     * Resize a VPS to a different plan.
     *
     * @return string 'success' or error message
     */
    public function changePackage()
    {
        try
        {
            $client = $this->getCubepathClient();
            $vpsId = $this->getVpsId();

            if (empty($vpsId))
            {
                return 'The VPS ID of this service is not set';
            }

            $newPlan = $this->params['configoption2'];
            if (empty($newPlan))
            {
                return 'New plan name is not configured';
            }

            // Upgrading only configurable options (backups) keeps the plan, and resizing to it fails.
            $vps = CubepathHelper::findVps($client, $vpsId);
            $currentPlan = $vps && isset($vps['plan']['plan_name']) ? $vps['plan']['plan_name'] : null;
            if ($currentPlan !== $newPlan)
            {
                $client->vps()->resize((int)$vpsId, $newPlan);
            }

            $this->syncBackups($client, (int)$vpsId);

            return 'success';
        }
        catch (\Cubepath\APIError $e)
        {
            return 'CubePath API Error: ' . $e->getMessage();
        }
        catch (\Exception $e)
        {
            return 'Error changing package: ' . $e->getMessage();
        }
    }

    /**
     * Turn automatic backups on or off to match what the service pays for,
     * keeping the schedule the client chose.
     */
    protected function syncBackups($client, $vpsId)
    {
        $purchased = CubepathHelper::backupsPurchased($this->params['serviceid']);
        if ($purchased === null)
        {
            return;
        }

        $settings = $client->vps()->backups()->getSettings($vpsId);
        if (!empty($settings['enabled']) === $purchased)
        {
            return;
        }

        $client->vps()->backups()->updateSettings($vpsId, array(
            'enabled'        => $purchased,
            'schedule_hour'  => isset($settings['schedule_hour']) ? (int)$settings['schedule_hour'] : 3,
            'retention_days' => isset($settings['retention_days']) ? (int)$settings['retention_days'] : 7,
            'max_backups'    => isset($settings['max_backups']) ? (int)$settings['max_backups'] : 7,
        ));
    }

    /**
     * ID of the client's public key in the CubePath account, adding it if
     * needed. The API refuses a key that is already there, so an existing
     * one is reused and left in place when the service ends.
     *
     * @return array{0: int, 1: bool} key ID, and whether it was created now
     */
    protected function sshKeyId($client, $publicKey)
    {
        if (!CubepathHelper::validSshKey($publicKey))
        {
            throw new \RuntimeException('The SSH public key of the order is not valid');
        }

        $parts = preg_split('/\s+/', trim($publicKey));
        $keys = $client->sshKeys()->list();
        foreach (isset($keys['sshkeys']) ? $keys['sshkeys'] : array() as $key)
        {
            $existing = preg_split('/\s+/', trim(isset($key['ssh_key']) ? (string)$key['ssh_key'] : ''));
            if (count($existing) >= 2 && $existing[0] === $parts[0] && $existing[1] === $parts[1])
            {
                return array((int)$key['id'], false);
            }
        }

        $created = $client->sshKeys()->create('whmcs-' . (int)$this->params['serviceid'] . '-' . time(), $publicKey);
        if (empty($created['ssh_key_id']))
        {
            throw new \RuntimeException('CubePath API did not return an SSH key ID');
        }

        return array((int)$created['ssh_key_id'], true);
    }

    /**
     * Start (power on) a VPS.
     *
     * @return string 'success' or error message
     */
    public function start()
    {
        return $this->powerAction('start');
    }

    /**
     * Reboot a VPS.
     *
     * @return string 'success' or error message
     */
    public function reboot()
    {
        return $this->powerAction('reboot');
    }

    /**
     * Stop (power off) a VPS.
     *
     * @return string 'success' or error message
     */
    public function stop()
    {
        return $this->powerAction('stop');
    }

    /**
     * Execute a power action on the VPS.
     *
     * @param string $action Power action: start, stop, reboot, reset
     * @return string 'success' or error message
     */
    protected function powerAction($action)
    {
        try
        {
            $client = $this->getCubepathClient();
            $vpsId = $this->getVpsId();

            if (empty($vpsId))
            {
                return 'The VPS ID of this service is not set';
            }

            CubepathHelper::power($client, $vpsId, $action);
            return 'success';
        }
        catch (\Cubepath\APIError $e)
        {
            return 'CubePath API Error: ' . $e->getMessage();
        }
        catch (\Exception $e)
        {
            return 'Error performing power action (' . $action . '): ' . $e->getMessage();
        }
    }

    /**
     * Hostname for the VPS: the service domain if it is a valid DNS name,
     * otherwise vps-<service id>.
     *
     * @return string
     */
    protected function hostname()
    {
        $domain = strtolower(trim(isset($this->params['domain']) ? $this->params['domain'] : ''));
        $label = '(?!-)[a-z0-9-]{1,63}(?<!-)';

        if (strlen($domain) <= 253 && preg_match('/^' . $label . '(\.' . $label . ')*$/', $domain))
        {
            return $domain;
        }

        return 'vps-' . $this->params['serviceid'];
    }

    /**
     * Service password to use as the VPS root password. The API requires at
     * least 8 characters; a stronger one is generated and saved if needed.
     *
     * @return string
     */
    protected function servicePassword()
    {
        $password = isset($this->params['password']) ? (string)$this->params['password'] : '';

        if (strlen($password) < 12)
        {
            $password = bin2hex(random_bytes(12));
            Capsule::table('tblhosting')
                ->where('id', $this->params['serviceid'])
                ->update(array('password' => encrypt($password)));
        }

        return $password;
    }

    /**
     * Get the VPS ID from the service's custom fields.
     *
     * @return string|null The VPS ID or null if not set
     */
    protected function getVpsId()
    {
        if (isset($this->params['customfields']['vps_id']) && !empty($this->params['customfields']['vps_id']))
        {
            return $this->params['customfields']['vps_id'];
        }

        return CubepathHelper::getCustomFieldValue($this->params['serviceid'], 'vps_id');
    }
}
