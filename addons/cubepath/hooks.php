<?php
/**
 * CubePath addon hooks.
 *
 * Location and template visibility is applied to the configurable
 * sub-options of CubePath products, so the order form needs no client-side
 * filtering. This hook re-applies it after an admin edits a product, in case
 * hidden sub-options were shown again by hand, and the cron keeps the
 * locations in step with CubePath's stock.
 */

use CubePath\WHMCS\Addon\Catalog;
use CubePath\WHMCS\Addon\ClientArea;
use CubePath\WHMCS\Addon\Products;
use CubePath\WHMCS\Addon\Settings;

if (!defined('WHMCS'))
{
    die('This file cannot be accessed directly');
}

add_hook('ProductEdit', 1, function ($vars) {
    if (!file_exists(__DIR__ . '/vendor/autoload.php'))
    {
        return;
    }

    require_once __DIR__ . '/vendor/autoload.php';

    if (in_array((int)$vars['pid'], array_map('intval', Products::ids()), true))
    {
        Products::applyVisibility();
    }
});

/**
 * Every 15 minutes, read the catalog again: sold out locations are hidden
 * from the order form, restocked ones shown, and new locations and
 * templates added to the products.
 */
add_hook('AfterCronJob', 1, function ($vars) {
    if (!file_exists(__DIR__ . '/vendor/autoload.php'))
    {
        return;
    }

    require_once __DIR__ . '/vendor/autoload.php';

    if (Settings::stockAge() < 900 || !Products::ids() || Settings::apiToken() === '')
    {
        return;
    }

    try
    {
        foreach (Products::syncAll(Catalog::fetch(new \Cubepath\CubepathClient(Settings::apiToken()))) as $error)
        {
            logActivity('CubePath: could not sync product ' . $error);
        }
    }
    catch (\Exception $e)
    {
        // Retried on the next run; the stock timestamp is only updated on success.
        logActivity('CubePath: could not read the catalog to update stock: ' . $e->getMessage());
    }
});

/**
 * CubePath client area (Addons > CubePath > Settings > Client area): a menu
 * with only the VPS and their management, the VPS groups in the cart, and
 * the data the cubepath theme shows as {$cp}.
 */
function cubepath_client_area()
{
    static $enabled;
    if ($enabled === null)
    {
        $enabled = false;
        if (file_exists(__DIR__ . '/vendor/autoload.php'))
        {
            require_once __DIR__ . '/vendor/autoload.php';
            $enabled = ClientArea::enabled();
        }
    }

    return $enabled;
}

add_hook('ClientAreaPrimaryNavbar', 1, function ($navbar) {
    if (cubepath_client_area())
    {
        ClientArea::primaryNavbar($navbar, !empty($_SESSION['uid']));
    }
});

add_hook('ClientAreaSecondarySidebar', 1, function ($sidebar) {
    if (cubepath_client_area() && defined('SHOPPING_CART'))
    {
        ClientArea::cartSidebar($sidebar);
    }
});

add_hook('ClientAreaPageCart', 1, function ($vars) {
    if (!cubepath_client_area() || $_SERVER['REQUEST_METHOD'] !== 'GET')
    {
        return;
    }

    $query = $_GET;
    if (!empty($vars['productGroup']['id']))
    {
        $query['gid'] = $vars['productGroup']['id'];
    }

    $url = ClientArea::cartRedirect($query);
    if ($url !== null)
    {
        header('Location: ' . ClientArea::systemUrl() . $url);
        exit;
    }
});

add_hook('ClientAreaPage', 1, function ($vars) {
    if (!cubepath_client_area())
    {
        return array();
    }

    // Domain renewals and pricing are routes of their own, so ClientAreaPageCart misses them.
    if ($_SERVER['REQUEST_METHOD'] === 'GET')
    {
        $route = isset($_GET['rp']) ? (string)$_GET['rp'] : (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $url = ClientArea::routeRedirect($route);
        if ($url !== null)
        {
            header('Location: ' . ClientArea::systemUrl() . $url);
            exit;
        }
    }

    return array('cp' => ClientArea::templateVars($vars));
});
