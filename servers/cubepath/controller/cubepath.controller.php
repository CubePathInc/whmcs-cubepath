<?php
/**
 * CubePath Cloud WHMCS Module - Base Controller
 *
 * All client area controllers extend this base class. Provides shared
 * functionality: API client access, database table management, template
 * variable handling, and VPS ID retrieval.
 */

use Illuminate\Database\Capsule\Manager as Capsule;

class CubepathController
{
    /** @var \Cubepath\CubepathClient|null CubePath API client */
    public $client;

    /** @var int WHMCS client ID */
    public $clientID;

    /** @var int WHMCS service (hosting) ID */
    public $serviceID;

    /** @var array WHMCS module parameters */
    public $params;

    /** @var array Template variables to pass to Smarty */
    protected $templateVars = array();

    /**
     * @param array $params WHMCS module parameters
     */
    public function __construct($params)
    {
        $this->params = $params;
        $this->clientID = $params['userid'];
        $this->serviceID = $params['serviceid'];
        $this->getCubepathClient();
        $this->createTables();
    }

    /**
     * Initialize the CubePath API client from the product's API token.
     *
     * @return bool True if the client was created successfully
     */
    public function getCubepathClient()
    {
        try
        {
            $this->client = new \Cubepath\CubepathClient($this->params['configoption1']);
            return true;
        }
        catch (\Exception $e)
        {
            return false;
        }
    }

    /**
     * Create module-specific database tables if they do not already exist.
     *
     * Tables created:
     *   - cubepath_sshkeys: Tracks SSH keys associated with clients
     *   - cubepath_dns: Tracks DNS zones associated with client services
     */
    public function createTables()
    {
        if (!Capsule::schema()->hasTable('cubepath_sshkeys'))
        {
            Capsule::schema()->create('cubepath_sshkeys', function ($table) {
                $table->increments('id');
                $table->integer('client_id');
                $table->string('ssh_key_id');
            });
        }

        if (!Capsule::schema()->hasTable('cubepath_dns'))
        {
            Capsule::schema()->create('cubepath_dns', function ($table) {
                $table->increments('id');
                $table->integer('client_id');
                $table->integer('service_id');
                $table->string('zone_uuid')->nullable();
                $table->string('domain');
            });
        }
    }

    /**
     * Get the VPS ID from the service's custom fields.
     *
     * @return string|null VPS ID or null if not found
     */
    public function getVpsId()
    {
        return CubepathHelper::getCustomFieldValue($this->serviceID, 'vps_id');
    }

    /**
     * Assign a variable to the template.
     *
     * @param string $key   Variable name
     * @param mixed  $value Variable value
     */
    public function assign($key, $value)
    {
        $this->templateVars[$key] = $value;
    }

    /**
     * Get all assigned template variables.
     *
     * @return array Template variables
     */
    public function getTemplateVars()
    {
        return $this->templateVars;
    }

    /**
     * Build a redirect URL for the client area and perform the redirect.
     *
     * @param string $controller Controller name
     * @param string $action     Action name
     */
    public function redirect($controller, $action)
    {
        $url = 'clientarea.php?action=productdetails'
            . '&id=' . $this->serviceID
            . '&cloudController=' . urlencode($controller)
            . '&cloudAction=' . urlencode($action);

        header('Location: ' . $url);
        die();
    }
}
