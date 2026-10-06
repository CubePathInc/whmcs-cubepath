<?php
/**
 * CubePath addon module for WHMCS.
 *
 * Holds the API credentials shared with the CubePath server module and
 * provides admin tools to create products from CubePath plans and choose
 * which locations and templates clients can order.
 *
 * @see https://cubepath.com
 */

use CubePath\WHMCS\Addon\Admin\Controller;
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
            'apiToken'         => array(
                'FriendlyName' => 'API Token',
                'Type'         => 'password',
                'Size'         => '60',
                'Description'  => 'CubePath API token, created from my.cubepath.com.',
            ),
            'defaultProjectId' => array(
                'FriendlyName' => 'Default Project ID',
                'Type'         => 'text',
                'Size'         => '10',
                'Description'  => 'CubePath project where new VPS are created.',
            ),
        ),
    );
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
