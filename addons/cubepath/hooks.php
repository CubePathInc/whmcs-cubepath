<?php
/**
 * CubePath addon hooks.
 *
 * Location and template visibility is applied to the configurable
 * sub-options of CubePath products, so the order form needs no client-side
 * filtering. This hook re-applies it after an admin edits a product, in case
 * hidden sub-options were shown again by hand.
 */

use CubePath\WHMCS\Addon\Products;

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
