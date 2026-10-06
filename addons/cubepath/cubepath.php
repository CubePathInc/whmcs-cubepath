<?php
/**
 * CubePath addon module for WHMCS.
 *
 * Uses the API token of the CubePath server in System Settings > Servers and
 * provides admin tools to create products from CubePath plans and choose
 * which locations and templates clients can order.
 *
 * @see https://cubepath.com
 */

use CubePath\WHMCS\Addon\Admin\Controller;
use CubePath\WHMCS\Addon\Projects;
use CubePath\WHMCS\Addon\Settings;

if (!defined('WHMCS'))
{
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/vendor/autoload.php';

defined('CUBEPATH_ADDON_VERSION') || define('CUBEPATH_ADDON_VERSION', '1.0.0');

function cubepath_config()
{
    return array(
        'name'        => 'CubePath',
        'description' => 'Create WHMCS products from CubePath VPS plans and manage the locations and templates offered to clients.',
        'version'     => CUBEPATH_ADDON_VERSION,
        'author'      => '<a href="https://cubepath.com" target="_blank">CubePath</a>',
        'language'    => 'english',
        'fields'      => array(
            'defaultProjectId' => cubepath_project_field(),
        ),
    );
}

/**
 * Default project setting: a dropdown of the token's projects once a token is saved.
 *
 * WHMCS calls cubepath_config() on many admin pages, so the API is only queried
 * on the addon configuration page.
 */
function cubepath_project_field()
{
    $field = array(
        'FriendlyName' => 'Default Project',
        'Type'         => 'text',
        'Size'         => '10',
        'Description'  => 'CubePath project where new VPS are created. Add a CubePath server in System Settings > Servers to choose it from a list.',
    );

    if (basename($_SERVER['SCRIPT_NAME']) !== 'configaddonmods.php' || Settings::apiToken() === '')
    {
        return $field;
    }

    try
    {
        $field['Type'] = 'dropdown';
        $field['Options'] = Projects::options(Projects::fetch(Settings::apiToken()), Settings::defaultProjectId());
        $field['Description'] = 'CubePath project where new VPS are created.';
        unset($field['Size']);
    }
    catch (Exception $e)
    {
        $field['Type'] = 'text';
        $field['Description'] = 'Could not list projects: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    }

    return $field;
}

function cubepath_activate()
{
    Settings::migrateLegacy();

    return array('status' => 'success', 'description' => 'CubePath addon activated.');
}

function cubepath_deactivate()
{
    return array('status' => 'success', 'description' => 'CubePath addon deactivated. Products and services are kept.');
}

function cubepath_output($vars)
{
    $controller = new Controller($vars['modulelink'], isset($vars['_lang']) ? $vars['_lang'] : array());

    echo $controller->dispatch($_GET, $_POST);
}
