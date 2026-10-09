<?php

namespace CubePath\WHMCS\Addon;

use WHMCS\Database\Capsule;

/**
 * Read and write the addon settings stored in tbladdonmodules.
 */
class Settings
{
    const MODULE = 'cubepath';

    const DISABLED_LOCATIONS = 'disabledLocations';
    const DISABLED_TEMPLATES = 'disabledTemplates';
    const FAMILY_GROUPS = 'familyGroups';

    /**
     * Settings written by the pre-release module, as PHP-serialized maps.
     */
    const LEGACY_KEYS = array(
        self::DISABLED_LOCATIONS => 'locationSettings',
        self::DISABLED_TEMPLATES => 'templateSettings',
    );

    public static function get($key, $default = '')
    {
        $value = Capsule::table('tbladdonmodules')
            ->where('module', self::MODULE)
            ->where('setting', $key)
            ->value('value');

        return $value === null ? $default : $value;
    }

    public static function set($key, $value)
    {
        Capsule::table('tbladdonmodules')->updateOrInsert(
            array('module' => self::MODULE, 'setting' => $key),
            array('value' => $value)
        );
    }

    public static function delete($key)
    {
        Capsule::table('tbladdonmodules')
            ->where('module', self::MODULE)
            ->where('setting', $key)
            ->delete();
    }

    /**
     * API token of the first CubePath server in System Settings > Servers.
     *
     * Installs from before servers were used kept the token in the addon
     * settings, as plain text; it is still read until a server is added.
     */
    public static function apiToken()
    {
        $token = Servers::token();

        return $token !== '' ? $token : trim(self::get('apiToken'));
    }

    /**
     * Save an API token to the CubePath server and drop the legacy addon copy,
     * so apiToken() reads the new one.
     */
    public static function setApiToken($token)
    {
        Servers::saveToken($token);
        self::delete('apiToken');
    }

    public static function defaultProjectId()
    {
        return trim(self::get('defaultProjectId'));
    }

    public static function setDefaultProjectId($projectId)
    {
        self::set('defaultProjectId', $projectId);
    }

    /**
     * @return string[] Location names hidden from the order form.
     */
    public static function disabledLocations()
    {
        return self::getList(self::DISABLED_LOCATIONS);
    }

    /**
     * @param string[] $names
     */
    public static function setDisabledLocations(array $names)
    {
        self::setList(self::DISABLED_LOCATIONS, $names);
    }

    /**
     * @return string[] Template names hidden from the order form.
     */
    public static function disabledTemplates()
    {
        return self::getList(self::DISABLED_TEMPLATES);
    }

    /**
     * @param string[] $names
     */
    public static function setDisabledTemplates(array $names)
    {
        self::setList(self::DISABLED_TEMPLATES, $names);
    }

    /**
     * Product group created for each plan family by the product creator, so
     * groups renamed by the admin keep receiving their family's products.
     *
     * @return array<string, int> family => product group ID
     */
    public static function familyGroups()
    {
        $decoded = json_decode(self::get(self::FAMILY_GROUPS, '{}'), true);

        return is_array($decoded) ? array_map('intval', $decoded) : array();
    }

    public static function setFamilyGroup($family, $groupId)
    {
        $groups = self::familyGroups();
        $groups[$family] = (int)$groupId;

        self::set(self::FAMILY_GROUPS, json_encode($groups));
    }

    /**
     * Locations where each plan is in stock, as last read from the API.
     *
     * @param Catalog $catalog
     */
    public static function setStock(Catalog $catalog)
    {
        $stock = array();
        foreach ($catalog->plans() as $name => $plan)
        {
            $stock[$name] = $plan['locations'];
        }

        self::set('stock', json_encode($stock));
        self::set('stockCheckedAt', (string)time());
    }

    /**
     * @return array<string, string[]> plan name => locations in stock
     */
    public static function stock()
    {
        $decoded = json_decode(self::get('stock', '{}'), true);

        return is_array($decoded) ? $decoded : array();
    }

    /**
     * Seconds since the stock was last read from the API.
     */
    public static function stockAge()
    {
        $checked = (int)self::get('stockCheckedAt', '0');

        return $checked > 0 ? time() - $checked : PHP_INT_MAX;
    }

    /**
     * Convert settings from the pre-release serialized format to JSON.
     */
    public static function migrateLegacy()
    {
        foreach (self::LEGACY_KEYS as $key => $legacyKey)
        {
            $legacy = self::get($legacyKey, null);
            if ($legacy === null)
            {
                continue;
            }

            $map = $legacy === '' ? false : @unserialize($legacy, array('allowed_classes' => false));
            if (is_array($map) && self::get($key, null) === null)
            {
                self::setList($key, array_keys($map));
            }

            self::delete($legacyKey);
        }
    }

    private static function getList($key)
    {
        $decoded = json_decode(self::get($key, '[]'), true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : array();
    }

    private static function setList($key, array $names)
    {
        $names = array_values(array_unique(array_filter(array_map('strval', $names), 'strlen')));
        sort($names);

        self::set($key, json_encode($names));
    }
}
