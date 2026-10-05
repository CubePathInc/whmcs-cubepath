<?php
/**
 * CubePath Cloud WHMCS Module - Hooks
 *
 * Registers WHMCS hooks for the CubePath server module. Currently handles
 * injecting custom CSS/JS into admin product configuration pages.
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
