<?php

namespace CubePath\WHMCS\Addon;

use WHMCS\Database\Capsule;

/**
 * The order form of CubePath products: what the configurator cards need to
 * replace WHMCS's own fields, and the checks on what the client entered.
 *
 * The cards (ui/src/views/store) only drive the native form fields, so the
 * cart validates, prices and saves the order as usual.
 */
class Store
{
    /** The API refuses larger cloud-init scripts. */
    const MAX_CLOUD_INIT_BYTES = 65536;

    /**
     * Configuration of the configurator cards for a product, or null when it
     * is not a CubePath product or the cards are turned off.
     *
     * @param int $productId
     * @param int $currencyId Currency the client is ordering in
     * @return array|null See ui/src/lib/types.ts StoreConfig
     */
    public static function config($productId, $currencyId)
    {
        $store = Settings::store();
        if (!$store['cards'] || !in_array((int)$productId, array_map('intval', Products::ids()), true))
        {
            return null;
        }

        $currency = Capsule::table('tblcurrencies')->where('id', $currencyId)->first()
            ?: Capsule::table('tblcurrencies')->where('default', 1)->first();

        $options = array();
        $subIds = array();
        $meta = Settings::templateMeta();

        $rows = Capsule::table('tblproductconfiglinks as l')
            ->join('tblproductconfigoptions as o', 'o.gid', '=', 'l.gid')
            ->where('l.pid', $productId)
            ->where('o.hidden', 0)
            ->orderBy('o.order')
            ->get(array('o.id', 'o.optionname'));

        foreach ($rows as $row)
        {
            $key = Products::optionValue($row->optionname);
            if (!in_array($key, array(Products::OPTION_LOCATION, Products::OPTION_TEMPLATE, Products::OPTION_NETWORK, Products::OPTION_BACKUPS), true))
            {
                continue;
            }

            $values = array();
            $subs = Capsule::table('tblproductconfigoptionssub')
                ->where('configid', $row->id)
                ->where('hidden', 0)
                ->orderBy('sortorder')
                ->get(array('id', 'optionname'));

            foreach ($subs as $sub)
            {
                $parts = explode('|', $sub->optionname, 2);
                $value = trim($parts[0]);
                $entry = array(
                    'id'    => (int)$sub->id,
                    'value' => $value,
                    'label' => isset($parts[1]) ? trim($parts[1]) : $value,
                );
                if ($key === Products::OPTION_TEMPLATE)
                {
                    $entry['kind'] = isset($meta[$value]['type']) ? $meta[$value]['type'] : (preg_match('/-\d/', $value) ? 'os' : 'app');
                    $entry['os'] = isset($meta[$value]['os']) && $meta[$value]['os'] !== '' ? $meta[$value]['os'] : strtok($value, '-');
                }
                $values[] = $entry;
                $subIds[] = (int)$sub->id;
            }

            $options[$key] = array('id' => (int)$row->id, 'values' => $values);
        }

        if (!isset($options[Products::OPTION_LOCATION], $options[Products::OPTION_TEMPLATE]))
        {
            return null;
        }

        $fields = array();
        foreach (array('sshKey' => 'ssh_key', 'cloudInit' => 'cloud_init') as $setting => $name)
        {
            $fields[$setting] = $store[$setting] ? self::customFieldId($productId, $name) : null;
        }

        return array(
            'mode'          => 'store',
            'assets'        => \CubepathHelper::systemUrl() . 'modules/servers/cubepath/assets/',
            'currency'      => array(
                'prefix' => $currency ? (string)$currency->prefix : '',
                'suffix' => $currency ? (string)$currency->suffix : '',
            ),
            'prices'        => $currency ? self::prices($subIds, (int)$currency->id) : (object)array(),
            'options'       => $options,
            'fields'        => $fields,
            'backupPercent' => $store['backupPercent'],
        );
    }

    /**
     * Errors in the CubePath fields of a cart item, as shown by the cart.
     *
     * @param int   $productId
     * @param array $post The configure product form
     * @return string[]
     */
    public static function validate($productId, array $post)
    {
        if (!in_array((int)$productId, array_map('intval', Products::ids()), true))
        {
            return array();
        }

        $errors = array();
        $custom = isset($post['customfield']) && is_array($post['customfield']) ? $post['customfield'] : array();
        $windows = stripos(self::chosenValue($productId, $post, Products::OPTION_TEMPLATE), 'windows') === 0;

        $sshKeyField = self::customFieldId($productId, 'ssh_key');
        $sshKey = $sshKeyField && isset($custom[$sshKeyField]) ? trim(html_entity_decode((string)$custom[$sshKeyField], ENT_QUOTES, 'UTF-8')) : '';
        if ($sshKey !== '')
        {
            if ($windows)
            {
                $errors[] = 'Windows servers do not use SSH keys. Remove the SSH key and use the root password instead.';
            }
            elseif (!\CubepathHelper::validSshKey($sshKey))
            {
                $errors[] = 'The SSH public key is not valid. Paste a single ssh-ed25519, ssh-rsa or ecdsa-sha2-nistp256 key on one line.';
            }
        }

        $cloudInitField = self::customFieldId($productId, 'cloud_init');
        $cloudInit = $cloudInitField && isset($custom[$cloudInitField]) ? trim(html_entity_decode((string)$custom[$cloudInitField], ENT_QUOTES, 'UTF-8')) : '';
        if (strlen($cloudInit) > self::MAX_CLOUD_INIT_BYTES)
        {
            $errors[] = 'The cloud-init script is larger than 64 KB.';
        }

        return $errors;
    }

    /**
     * API value of the sub-option chosen for one of the product's options.
     */
    private static function chosenValue($productId, array $post, $key)
    {
        $chosen = isset($post['configoption']) && is_array($post['configoption']) ? $post['configoption'] : array();

        $optionId = Capsule::table('tblproductconfiglinks as l')
            ->join('tblproductconfigoptions as o', 'o.gid', '=', 'l.gid')
            ->where('l.pid', $productId)
            ->where('o.optionname', 'like', $key . '|%')
            ->value('o.id');

        if (!$optionId || !isset($chosen[$optionId]))
        {
            return '';
        }

        $name = Capsule::table('tblproductconfigoptionssub')
            ->where('configid', $optionId)
            ->where('id', (int)$chosen[$optionId])
            ->value('optionname');

        return $name ? Products::optionValue($name) : '';
    }

    private static function customFieldId($productId, $name)
    {
        $id = Capsule::table('tblcustomfields')
            ->where('type', 'product')
            ->where('relid', $productId)
            ->where('fieldname', 'like', $name . '|%')
            ->value('id');

        return $id ? (int)$id : null;
    }

    /**
     * @return array<int, array<string, float>> sub-option ID => billing cycle => price
     */
    private static function prices(array $subIds, $currencyId)
    {
        $prices = array();
        if (!$subIds)
        {
            return $prices;
        }

        $rows = Capsule::table('tblpricing')
            ->where('type', 'configoptions')
            ->where('currency', $currencyId)
            ->whereIn('relid', $subIds)
            ->get();

        foreach ($rows as $row)
        {
            foreach (Products::BILLING_CYCLES as $cycle)
            {
                $prices[(int)$row->relid][$cycle] = (float)$row->{$cycle};
            }
        }

        return $prices;
    }
}
