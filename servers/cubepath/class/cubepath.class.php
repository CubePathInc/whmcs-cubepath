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

            $result = $client->vps()->create($projectId, array(
                'name'          => $hostname,
                'label'         => $hostname,
                'plan_name'     => $planName,
                'template_name' => $template,
                'location_name' => $location,
                'password'      => $password,
            ));

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

            $client->vps()->resize((int)$vpsId, $newPlan);
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
