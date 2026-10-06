<?php
/**
 * CubePath Cloud WHMCS Module - Client Area Renderer
 *
 * Dispatches client area requests to the appropriate controller and action,
 * then assembles the template response for WHMCS rendering.
 */

class CubepathRender
{
    /** @var string Template path relative to the template/ directory */
    protected $template = 'default';

    /** @var bool Whether a module error has occurred */
    protected $moduleError = false;

    /** @var string Module error message */
    protected $moduleErrorMessage = '';

    /** @var array WHMCS module parameters */
    private $params = array();

    /** @var string Controller name (e.g., 'Main') */
    private $controllerName = 'Main';

    /** @var string Action name (e.g., 'index') */
    private $actionName = 'index';

    /** @var string Full controller class name (e.g., 'MainController') */
    private $controllerFullName = 'MainController';

    /** @var string Full action method name (e.g., 'indexAction') */
    private $actionFullName = 'indexAction';

    /** @var array Smarty template variables */
    private $smartyVars = array();

    /** @var array|null Controller render result */
    private $render;

    /**
     * @param array $params WHMCS module parameters
     */
    function __construct($params)
    {
        $this->params = $params;
    }

    /**
     * Render the client area based on the given mode.
     *
     * @param string $mode Rendering mode ('ClientArea')
     * @return array Template file path and Smarty variables for WHMCS
     */
    public function render($mode)
    {
        switch ($mode)
        {
            case 'ClientArea':
                $this->renderCA();
                break;
            default:
                $this->setModuleErrorMessage(LangHelper::T('core.client.action_not_found'));
                break;
        }
        return $this->renderTemplate();
    }

    /**
     * Process a client area request by routing to the correct controller and action.
     *
     * Reads cloudController and cloudAction from GET parameters, sanitizes them,
     * instantiates the controller, and invokes the action method.
     */
    private function renderCA()
    {
        // Read and sanitize controller name from GET
        if (isset($_GET['cloudController']))
        {
            $rawController = filter_input(INPUT_GET, 'cloudController');
            $rawController = preg_replace('/[^a-zA-Z]/', '', $rawController);
            $this->controllerName = ucfirst($rawController);
            $this->controllerFullName = $this->controllerName . 'Controller';
        }

        // Read and sanitize action name from GET
        if (isset($_GET['cloudAction']))
        {
            $rawAction = filter_input(INPUT_GET, 'cloudAction');
            $rawAction = preg_replace('/[^a-zA-Z]/', '', $rawAction);
            $this->actionName = $rawAction;
            $this->actionFullName = $rawAction . 'Action';
        }

        // Load the controller file if it exists
        $controllerFile = CUBEPATHDIR . 'controller' . DS . strtolower($this->controllerName) . '.controller.php';
        if (file_exists($controllerFile))
        {
            require_once $controllerFile;
        }

        if (class_exists($this->controllerFullName))
        {
            $controller = new $this->controllerFullName($this->params);

            if (method_exists($controller, $this->actionFullName))
            {
                // Set the template path based on controller/action
                $this->setTemplate('controller' . DS . strtolower($this->controllerName) . DS . strtolower($this->actionName));
                $this->setVars('controller', $this->controllerName);
                $this->setVars('action', $this->actionName);
                $this->setVars('postData', $_POST);

                // Execute the controller action
                $this->render = $controller->{$this->actionFullName}();
            }
            else
            {
                $this->setModuleErrorMessage(LangHelper::T('core.client.action_not_found'));
            }
        }
        else
        {
            $this->setModuleErrorMessage(LangHelper::T('core.client.controller_not_found'));
        }
    }

    /**
     * Set the template file path (relative to template/ directory, without .tpl extension).
     *
     * @param string $template Template path
     */
    private function setTemplate($template)
    {
        $this->template = $template;
    }

    /**
     * Set a Smarty template variable.
     *
     * @param string $name  Variable name
     * @param mixed  $value Variable value
     */
    public function setVars($name, $value)
    {
        $this->smartyVars[$name] = $value;
    }

    /**
     * Set a module error message and switch to the error template.
     *
     * @param string $message Error message to display
     */
    public function setModuleErrorMessage($message)
    {
        $this->setTemplate('element' . DS . 'moduleError');
        $this->moduleError = true;
        $this->moduleErrorMessage = $message;
    }

    /**
     * Assemble the final template response array for WHMCS.
     *
     * Merges controller-returned template overrides, variables, and error messages
     * into the final output array containing 'templatefile' and 'vars'.
     *
     * @return array WHMCS template response with 'templatefile' and 'vars' keys
     */
    private function renderTemplate()
    {
        // Apply overrides from controller render result
        if (isset($this->render['templatefile']))
        {
            $this->setTemplate($this->render['templatefile']);
        }
        if (isset($this->render['error']))
        {
            $this->setModuleErrorMessage($this->render['error']);
        }
        if (isset($this->render['vars']))
        {
            foreach ($this->render['vars'] as $key => $value)
            {
                $this->setVars($key, $value);
            }
        }

        // Verify the template file exists
        if (file_exists(CUBEPATHDIR . 'template' . DS . $this->template . '.tpl'))
        {
            $this->setVars('_LANG', LangHelper::T());
            $this->setVars('module', $this->params);
        }
        else
        {
            $this->setModuleErrorMessage(LangHelper::T('core.action.template_not_found'));
        }

        // Include error information if an error occurred
        if ($this->moduleError)
        {
            $this->setVars('moduleError', $this->moduleErrorMessage);
        }

        // Include flash messages from the session
        $this->setVars('flashMessages', SessionHelper::getFlashMessages());

        return array(
            'templatefile' => 'template' . DS . $this->template,
            'vars' => $this->smartyVars,
        );
    }
}
