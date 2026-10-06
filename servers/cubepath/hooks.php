<?php
/**
 * CubePath Cloud WHMCS Module - Hooks
 *
 * Registers WHMCS hooks for the CubePath server module: admin assets on the
 * product configuration page and the product's options when it is saved.
 */

use Illuminate\Database\Capsule\Manager as Capsule;

require 'loader.php';

/**
 * Inject CubePath-specific JavaScript into the admin area when editing
 * a product that uses the CubePath server module.
 */
add_hook('AdminAreaHeadOutput', 1, function ($params) {

    // Only apply on the product configuration edit page
    if ($params['filename'] != 'configproducts'
        || !isset($_GET['action'])
        || $_GET['action'] != 'edit'
        || !isset($_GET['id']))
    {
        return '';
    }

    $productID = (int)$_GET['id'];

    // Verify this product uses the CubePath server module
    $product = Capsule::table('tblproducts')
        ->select('id')
        ->where('servertype', 'cubepath')
        ->where('id', $productID)
        ->first();

    if (!$product)
    {
        return '';
    }

    // Ensure custom fields exist for this product
    CubepathHelper::addCustomFields($productID);

    // Inject admin JS if available
    $jsFile = __DIR__ . DS . 'assets' . DS . 'js' . DS . 'configproducts.js';
    if (file_exists($jsFile))
    {
        $script = str_replace('#id#', $productID, file_get_contents($jsFile));
        return '<script type="text/javascript">' . $script . '</script>';
    }

    return '';
});

/**
 * When a CubePath product is saved, create its admin custom fields and the
 * location and operating system configurable options for its plan.
 */
add_hook('ProductEdit', 1, function ($vars) {

    $productId = isset($vars['pid']) ? (int)$vars['pid'] : 0;
    $product = Capsule::table('tblproducts')
        ->where('id', $productId)
        ->where('servertype', 'cubepath')
        ->first();

    if (!$product || trim((string)$product->configoption2) === '')
    {
        return;
    }

    try
    {
        $token = \CubePath\WHMCS\Addon\Servers::productToken($productId);
        if ($token === '')
        {
            throw new \RuntimeException('no CubePath server in the product\'s server group');
        }

        \CubePath\WHMCS\Addon\Products::ensureCustomFields($productId);
        \CubePath\WHMCS\Addon\Products::syncConfigurableOptions(
            $productId,
            \CubePath\WHMCS\Addon\Catalog::fetch(new \Cubepath\CubepathClient($token))
        );
    }
    catch (\Exception $e)
    {
        logActivity(sprintf('CubePath: could not create the options of product #%d: %s', $productId, $e->getMessage()));
    }
});
