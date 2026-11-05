<?php

namespace CubePath\WHMCS\Addon\Admin;

/**
 * The WHMCS role permission each action of the admin panels needs.
 *
 * WHMCS grants an addon with one checkbox per role, and support roles often get
 * it to see the list of servers. That checkbox alone must not open root
 * passwords, power actions or the API token, so each action also asks for the
 * permission WHMCS itself would ask for. An action not listed here is refused.
 */
class AdminPermissions
{
    /** Nothing beyond access to the addon. */
    const ADDON_ACCESS = array();

    /** Read a client's services. */
    const VIEW_SERVICES = array('View Clients Products/Services');

    /** Reveal the root password, which goes further than viewing the service. */
    const EDIT_SERVICES = array('Edit Clients Products/Services');

    /**
     * Change a server: power, reinstall, suspend, firewall, backups. WHMCS code has
     * long called it "Perform Server Operations" and its current documentation
     * "Perform Module Command Operations", so a role with either may act.
     */
    const OPERATE_SERVERS = array('Perform Server Operations', 'Perform Module Command Operations');

    /** Change what applies to the whole module: API token, products, store, client area. */
    const CONFIGURE_MODULE = array('Configure Servers');

    /** Any one permission of the list is enough. */
    const ACTIONS = array(
        'servers.list'         => self::VIEW_SERVICES,
        'servers.bulk'         => self::OPERATE_SERVERS,

        'overview'             => self::VIEW_SERVICES,
        'metrics'              => self::VIEW_SERVICES,
        'network'              => self::VIEW_SERVICES,
        'firewall'             => self::VIEW_SERVICES,
        'backups'              => self::VIEW_SERVICES,
        'isos'                 => self::VIEW_SERVICES,
        'templates'            => self::VIEW_SERVICES,
        'activity'             => self::VIEW_SERVICES,

        'credentials'          => self::EDIT_SERVICES,

        'power'                => self::OPERATE_SERVERS,
        'password'             => self::OPERATE_SERVERS,
        'label'                => self::OPERATE_SERVERS,
        'rdns'                 => self::OPERATE_SERVERS,
        'firewall.save'        => self::OPERATE_SERVERS,
        'backups.create'       => self::OPERATE_SERVERS,
        'backups.restore'      => self::OPERATE_SERVERS,
        'backups.delete'       => self::OPERATE_SERVERS,
        'backups.settings'     => self::OPERATE_SERVERS,
        'backups.order'        => self::OPERATE_SERVERS,
        'iso.mount'            => self::OPERATE_SERVERS,
        'iso.unmount'          => self::OPERATE_SERVERS,
        'reinstall'            => self::OPERATE_SERVERS,

        'addon.dashboard'      => self::ADDON_ACCESS,
        'addon.creator'        => self::ADDON_ACCESS,
        'addon.products'       => self::ADDON_ACCESS,
        'addon.toggles'        => self::ADDON_ACCESS,
        'addon.store'          => self::ADDON_ACCESS,
        'addon.clientArea'     => self::ADDON_ACCESS,

        'addon.settings'       => self::CONFIGURE_MODULE,
        'addon.saveToken'      => self::CONFIGURE_MODULE,
        'addon.saveProject'    => self::CONFIGURE_MODULE,
        'addon.saveStore'      => self::CONFIGURE_MODULE,
        'addon.saveClientArea' => self::CONFIGURE_MODULE,
        'addon.saveToggles'    => self::CONFIGURE_MODULE,
        'addon.createProduct'  => self::CONFIGURE_MODULE,
        'addon.createAllProducts' => self::CONFIGURE_MODULE,
        'addon.syncProducts'   => self::CONFIGURE_MODULE,
        'addon.deleteProduct'  => self::CONFIGURE_MODULE,
        'addon.deleteGroup'    => self::CONFIGURE_MODULE,
    );

    /**
     * Whether the signed-in administrator may run an action of the panels.
     *
     * @param string $action
     * @return bool
     */
    public static function allows($action)
    {
        if (!isset(self::ACTIONS[$action]))
        {
            return false;
        }

        $permissions = self::ACTIONS[$action];
        if (!$permissions)
        {
            return true;
        }

        if (!function_exists('checkPermission'))
        {
            return false;
        }

        foreach ($permissions as $permission)
        {
            if (checkPermission($permission, true))
            {
                return true;
            }
        }

        return false;
    }
}
