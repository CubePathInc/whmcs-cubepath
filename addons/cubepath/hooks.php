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
