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
     * Create and return a CubePath API client using the configured API token.
     *
     * @return \Cubepath\CubepathClient
     * @throws \InvalidArgumentException If API token is empty
     */
    public function getCubepathClient()
    {
        if ($this->client === null)
        {
            $this->client = new \Cubepath\CubepathClient($this->params['configoption1']);
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

            // Use the WHMCS domain field as the hostname
            $hostname = $this->params['domain'];
            if (empty($hostname))
            {
                $hostname = 'vps-' . $this->params['serviceid'];
            }

            // Create the VPS via CubePath API
            $createParams = array(
                'name'          => $hostname,
                'plan_name'     => $planName,
                'template_name' => $template,
                'location_name' => $location,
            );

            $result = $client->vps()->create($projectId, $createParams);

            // Extract VPS ID from the creation response
            $vpsId = '';
            if (isset($result['detail']['id']))
            {
                $vpsId = $result['detail']['id'];
            }
            elseif (isset($result['id']))
            {
                $vpsId = $result['id'];
            }

            if (!empty($vpsId))
            {
                // Store the VPS ID in a custom field for future operations
                CubepathHelper::setCustomFieldValue($this->params['serviceid'], 'vps_id', $vpsId);

                // Attempt to fetch VPS details to get the IP address
                try
                {
                    // Allow a moment for the VPS to initialize
                    sleep(5);
                    $vpsDetails = $client->vps()->get((int)$vpsId);

                    if (isset($vpsDetails['ip_address']) && !empty($vpsDetails['ip_address']))
                    {
                        $ipAddress = $vpsDetails['ip_address'];
                        CubepathHelper::setCustomFieldValue($this->params['serviceid'], 'ip_address', $ipAddress);

                        // Also update the dedicated IP field in tblhosting
                        Capsule::table('tblhosting')
                            ->where('id', $this->params['serviceid'])
                            ->update(array('dedicatedip' => $ipAddress));
                    }
                }
                catch (\Exception $e)
                {
                    // IP retrieval is non-critical; the VPS was still created successfully
                }
            }

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
                return LangHelper::T('core.action.not_found_vps_id');
            }

            $client->vps()->power((int)$vpsId, 'stop');
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
                return LangHelper::T('core.action.not_found_vps_id');
            }

            $client->vps()->power((int)$vpsId, 'start');
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
                return LangHelper::T('core.action.not_found_vps_id');
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
                return LangHelper::T('core.action.not_found_vps_id');
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
                return LangHelper::T('core.action.not_found_vps_id');
            }

            $client->vps()->power((int)$vpsId, $action);
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
