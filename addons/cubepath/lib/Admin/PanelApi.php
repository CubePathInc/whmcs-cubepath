<?php

namespace CubePath\WHMCS\Addon\Admin;

use CubePath\WHMCS\Addon\Products;
use Exception;
use WHMCS\Database\Capsule;

/**
 * JSON endpoint of the admin panels (addonmodules.php?module=cubepath&cpapi=1):
 * the service panel on the admin service page and the addon pages.
 * WHMCS has already checked that the admin may use this addon.
 */
class PanelApi
{
    /** Services listed in the reseller view. */
    const LISTED_STATUSES = array('Pending', 'Active', 'Suspended');

    const BULK_ACTIONS = array('start', 'stop', 'reboot', 'suspend', 'unsuspend');
    const BULK_LIMIT = 50;

    /**
     * Answer the request and exit.
     */
    public static function handle(array $post, array $lang)
    {
        require_once ROOTDIR . '/modules/servers/cubepath/loader.php';

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !\CubepathHelper::validToken(isset($post['token']) ? $post['token'] : ''))
        {
            \PanelController::sendJson(array('ok' => false, 'error' => 'Your session expired. Reload the page and try again.'));
        }

        $action = isset($post['cpaction']) ? (string)$post['cpaction'] : '';
        // WHMCS runs htmlspecialchars() over $_POST, which breaks the JSON quotes.
        $data = json_decode(htmlspecialchars_decode(isset($post['cpdata']) ? (string)$post['cpdata'] : '{}', ENT_QUOTES), true);
        $data = is_array($data) ? $data : array();

        try
        {
            if ($action === 'servers.list')
            {
                \PanelController::sendJson(array('ok' => true, 'data' => self::listServers()));
            }
            if ($action === 'servers.bulk')
            {
                \PanelController::sendJson(array('ok' => true, 'data' => self::bulk($data)));
            }
            if (strpos($action, 'addon.') === 0)
            {
                $api = new AddonApi($lang);
                \PanelController::sendJson(array('ok' => true, 'data' => $api->handle(substr($action, 6), $data)));
            }

            $params = self::serviceParams(isset($post['serviceid']) ? (int)$post['serviceid'] : 0);
        }
        catch (Exception $e)
        {
            \PanelController::sendJson(array('ok' => false, 'error' => $e->getMessage()));
        }

        \PanelController::respond($params, \PanelController::ACTOR_ADMIN, $action, json_encode($data));
    }

    /**
     * WHMCS module parameters of a CubePath service.
     *
     * @param int $serviceId
     * @return array
     * @throws Exception
     */
    private static function serviceParams($serviceId)
    {
        $module = Capsule::table('tblhosting as h')
            ->join('tblproducts as p', 'p.id', '=', 'h.packageid')
            ->where('h.id', $serviceId)
            ->value('p.servertype');

        if ($module !== Products::SERVER_MODULE)
        {
            throw new Exception('Service not found.');
        }

        $server = new \WHMCS\Module\Server();
        if (!$server->loadByServiceID($serviceId))
        {
            throw new Exception('Service not found.');
        }

        return $server->buildParams();
    }

    /**
     * Every CubePath service with its live VPS state.
     */
    private static function listServers()
    {
        $rows = Capsule::table('tblhosting as h')
            ->join('tblproducts as p', 'p.id', '=', 'h.packageid')
            ->join('tblclients as c', 'c.id', '=', 'h.userid')
            ->where('p.servertype', Products::SERVER_MODULE)
            ->whereIn('h.domainstatus', self::LISTED_STATUSES)
            ->orderBy('h.id', 'desc')
            ->get(array(
                'h.id', 'h.userid', 'h.domain', 'h.domainstatus', 'h.server', 'h.packageid',
                'p.name as product', 'p.configoption1',
                'c.firstname', 'c.lastname', 'c.companyname', 'c.email',
            ));

        $vpsIds = self::vpsIds(array_map(function ($row) {
            return (int)$row->id;
        }, $rows->all()));

        $errors = array();
        $servers = array();
        foreach ($rows as $row)
        {
            $vps = null;
            $vpsId = isset($vpsIds[(int)$row->id]) ? $vpsIds[(int)$row->id] : 0;
            $token = self::serviceToken($row);
            if ($vpsId && $token !== '')
            {
                try
                {
                    $raw = \CubepathHelper::findVps(\CubepathHelper::client(array('configoption1' => $token)), $vpsId);
                    $vps = $raw ? \CubepathHelper::normalizeVps($raw) : null;
                }
                catch (Exception $e)
                {
                    $errors[$e->getMessage()] = true;
                }
            }

            $name = trim($row->firstname . ' ' . $row->lastname);
            $servers[] = array(
                'serviceId'     => (int)$row->id,
                'clientId'      => (int)$row->userid,
                'client'        => $row->companyname ? $name . ' (' . $row->companyname . ')' : $name,
                'email'         => (string)$row->email,
                'product'       => (string)$row->product,
                'domain'        => (string)$row->domain,
                'serviceStatus' => (string)$row->domainstatus,
                'vps'           => $vps,
            );
        }

        if ($errors && !array_filter($servers, function ($s) {
            return $s['vps'] !== null;
        }))
        {
            throw new Exception('Could not load servers from CubePath: ' . implode('; ', array_keys($errors)));
        }

        return $servers;
    }

    /**
     * @param int[] $serviceIds
     * @return array<int, int> service id => VPS id
     */
    private static function vpsIds(array $serviceIds)
    {
        if (!$serviceIds)
        {
            return array();
        }

        $values = Capsule::table('tblcustomfieldsvalues as v')
            ->join('tblcustomfields as f', 'f.id', '=', 'v.fieldid')
            ->where('f.type', 'product')
            ->where('f.fieldname', 'like', 'vps_id|%')
            ->whereIn('v.relid', $serviceIds)
            ->get(array('v.relid', 'v.value'));

        $ids = array();
        foreach ($values as $value)
        {
            if (ctype_digit((string)$value->value))
            {
                $ids[(int)$value->relid] = (int)$value->value;
            }
        }

        return $ids;
    }

    /**
     * Token a service provisions with: the product override, else its
     * assigned server, else the first CubePath server of the product's group.
     */
    private static function serviceToken($row)
    {
        static $servers = array();

        if (trim((string)$row->configoption1) !== '')
        {
            return trim((string)$row->configoption1);
        }

        $serverId = (int)$row->server;
        if ($serverId)
        {
            if (!array_key_exists($serverId, $servers))
            {
                $password = Capsule::table('tblservers')->where('id', $serverId)->where('type', Products::SERVER_MODULE)->value('password');
                $servers[$serverId] = $password !== null ? trim((string)decrypt($password)) : '';
            }
            if ($servers[$serverId] !== '')
            {
                return $servers[$serverId];
            }
        }

        return \CubePath\WHMCS\Addon\Servers::productToken((int)$row->packageid);
    }

    /**
     * Run a power or suspension action on several services.
     */
    private static function bulk(array $data)
    {
        $action = isset($data['action']) ? (string)$data['action'] : '';
        if (!in_array($action, self::BULK_ACTIONS, true))
        {
            throw new Exception('Unknown action.');
        }

        $ids = array_values(array_unique(array_map('intval', isset($data['ids']) && is_array($data['ids']) ? $data['ids'] : array())));
        if (!$ids || count($ids) > self::BULK_LIMIT)
        {
            throw new Exception('Select between 1 and ' . self::BULK_LIMIT . ' servers.');
        }

        $ok = 0;
        $failed = array();
        foreach ($ids as $serviceId)
        {
            try
            {
                if ($action === 'suspend' || $action === 'unsuspend')
                {
                    $result = localAPI($action === 'suspend' ? 'ModuleSuspend' : 'ModuleUnsuspend', array('serviceid' => $serviceId));
                    if (!isset($result['result']) || $result['result'] !== 'success')
                    {
                        throw new Exception(isset($result['message']) ? $result['message'] : 'failed');
                    }
                }
                else
                {
                    $controller = new \PanelController(self::serviceParams($serviceId), \PanelController::ACTOR_ADMIN);
                    $controller->handle('power', array('action' => $action));
                }
                $ok++;
            }
            catch (\Cubepath\APIError $e)
            {
                $failed[] = array('serviceId' => $serviceId, 'error' => $e->getDetail() ?: $e->getMessage());
            }
            catch (Exception $e)
            {
                $failed[] = array('serviceId' => $serviceId, 'error' => $e->getMessage());
            }
        }

        return array('ok' => $ok, 'failed' => $failed);
    }
}
