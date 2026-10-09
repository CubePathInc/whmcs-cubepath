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

/**
 * Order form: replace the location, operating system, network, backups,
 * SSH key and cloud-init fields of CubePath products with the configurator
 * cards (ui/src/views/store). The native fields stay in the form, hidden,
 * so they are used as is if the script does not run.
 */
add_hook('ClientAreaFooterOutput', 1, function ($vars) {

    if (!isset($vars['filename'], $vars['templatefile'])
        || $vars['filename'] !== 'cart'
        || $vars['templatefile'] !== 'configureproduct'
        || empty($vars['productinfo']['pid']))
    {
        return '';
    }

    try
    {
        $currencyId = isset($vars['currency']['id']) ? (int)$vars['currency']['id'] : 0;
        $config = \CubePath\WHMCS\Addon\Store::config((int)$vars['productinfo']['pid'], $currencyId);
        if (!$config)
        {
            return '';
        }

        $config['endpoint'] = '';
        $config['token'] = '';
        $config['lang'] = isset($vars['language']) ? (string)$vars['language'] : '';

        return CubepathHelper::panelHtml($config, 'store.js');
    }
    catch (\Exception $e)
    {
        logActivity('CubePath: could not load the order form cards: ' . $e->getMessage());

        return '';
    }
});

/**
 * Check the SSH key and cloud-init of a CubePath product before it goes in the cart.
 */
add_hook('ShoppingCartValidateProductUpdate', 1, function ($vars) {

    $index = isset($_POST['i']) ? (int)$_POST['i'] : -1;
    if (!isset($_SESSION['cart']['products'][$index]['pid']))
    {
        return array();
    }

    return \CubePath\WHMCS\Addon\Store::validate((int)$_SESSION['cart']['products'][$index]['pid'], $_POST);
});

/**
 * SSH keys added for terminated services cannot be deleted until CubePath
 * has destroyed the VPS that uses them; retry them once a day.
 */
add_hook('DailyCronJob', 1, function ($vars) {

    $rows = Capsule::table('tblcustomfieldsvalues as v')
        ->join('tblcustomfields as f', 'f.id', '=', 'v.fieldid')
        ->join('tblhosting as h', 'h.id', '=', 'v.relid')
        ->join('tblproducts as p', 'p.id', '=', 'h.packageid')
        ->where('f.type', 'product')
        ->where('f.fieldname', 'like', 'ssh_key_id|%')
        ->where('v.value', '!=', '')
        ->where('p.servertype', 'cubepath')
        ->whereIn('h.domainstatus', array('Terminated', 'Cancelled', 'Fraud'))
        ->get(array('v.fieldid', 'v.relid', 'v.value', 'h.packageid'));

    foreach ($rows as $row)
    {
        try
        {
            $token = \CubePath\WHMCS\Addon\Servers::productToken((int)$row->packageid);
            if ($token !== '' && CubepathHelper::deleteSshKey(new \Cubepath\CubepathClient($token), (int)$row->value))
            {
                Capsule::table('tblcustomfieldsvalues')
                    ->where('fieldid', $row->fieldid)
                    ->where('relid', $row->relid)
                    ->update(array('value' => ''));
            }
        }
        catch (\Exception $e)
        {
            logActivity('CubePath: could not delete the SSH key of service #' . (int)$row->relid . ': ' . $e->getMessage());
        }
    }
});

/**
 * Service page of a CubePath product: hide the configurable options and
 * additional information tabs, the panel in the server information tab
 * already shows the location, operating system and IPs.
 */
add_hook('ClientAreaPageProductDetails', 1, function ($vars) {

    $serviceId = isset($vars['id']) ? (int)$vars['id'] : 0;
    $isCubepath = $serviceId > 0 && Capsule::table('tblhosting as h')
        ->join('tblproducts as p', 'p.id', '=', 'h.packageid')
        ->where('h.id', $serviceId)
        ->where('p.servertype', 'cubepath')
        ->exists();

    if (!$isCubepath)
    {
        return array();
    }

    return array(
        'configurableoptions' => array(),
        'customfields'        => array(),
    );
});
