<?php

namespace CubePath\WHMCS\Addon;

use WHMCS\Database\Capsule;

/**
 * CubePath servers configured in System Settings > Servers. Each one holds
 * an API token in its password field.
 */
class Servers
{
    const GROUP_NAME = 'CubePath';

    /**
     * First enabled CubePath server, or null when there is none.
     */
    public static function first()
    {
        return Capsule::table('tblservers')
            ->where('type', Products::SERVER_MODULE)
            ->where('disabled', 0)
            ->orderBy('id')
            ->first();
    }

    /**
     * API token of the first enabled CubePath server.
     */
    public static function token()
    {
        $server = self::first();

        return $server ? trim((string)decrypt($server->password)) : '';
    }

    /**
     * Server group that new products are assigned to: the first group with
     * a CubePath server, or a new one holding every CubePath server.
     *
     * @return int Group ID, or 0 when no CubePath server exists
     */
    public static function groupId()
    {
        $serverIds = Capsule::table('tblservers')
            ->where('type', Products::SERVER_MODULE)
            ->pluck('id')
            ->all();

        if (!$serverIds)
        {
            return 0;
        }

        $groupId = Capsule::table('tblservergroupsrel')
            ->whereIn('serverid', $serverIds)
            ->orderBy('groupid')
            ->value('groupid');

        if ($groupId)
        {
            return (int)$groupId;
        }

        $groupId = Capsule::table('tblservergroups')->insertGetId(array(
            'name'     => self::GROUP_NAME,
            'filltype' => 1,
        ));
        foreach ($serverIds as $serverId)
        {
            Capsule::table('tblservergroupsrel')->insert(array('groupid' => $groupId, 'serverid' => $serverId));
        }

        return (int)$groupId;
    }

    /**
     * API token a product provisions with: its own override in configoption1,
     * otherwise the first CubePath server of its server group.
     */
    public static function productToken($productId)
    {
        $product = Capsule::table('tblproducts')->where('id', $productId)->first();
        if (!$product)
        {
            return '';
        }

        if (trim((string)$product->configoption1) !== '')
        {
            return trim((string)$product->configoption1);
        }

        $password = Capsule::table('tblservergroupsrel as r')
            ->join('tblservers as s', 's.id', '=', 'r.serverid')
            ->where('r.groupid', $product->servergroup)
            ->where('s.type', Products::SERVER_MODULE)
            ->where('s.disabled', 0)
            ->orderBy('s.id')
            ->value('s.password');

        return $password !== null ? trim((string)decrypt($password)) : '';
    }
}
