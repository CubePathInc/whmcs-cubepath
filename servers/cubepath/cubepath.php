<?php
/**
 * CubePath Cloud WHMCS Server Module
 *
 * Provides VPS provisioning, management, and client area functionality
 * for CubePath Cloud services within WHMCS.
 *
 * @see https://cubepath.com
 */

require 'loader.php';

/**
 * Module metadata for WHMCS.
 *
 * @return array
 */
function cubepath_MetaData()
{
    return array(
        'DisplayName' => 'CubePath Cloud VPS',
        'APIVersion' => '1.1',
        'RequiresServer' => true,
    );
}

/**
 * Product configuration options displayed in the WHMCS admin product setup.
 *
 * The plan and project are chosen from lists loaded from the API with the
 * token of the CubePath server in the product's server group. Their order
 * sets the configoptionN they are stored in, so it must not change.
 *
 * @param array $params WHMCS module parameters
 * @return array Configuration field definitions
 */
function cubepath_ConfigOptions($params)
{
    return array(
        // configoption1
        'api_token' => array(
            'FriendlyName' => 'API Token',
            'Type' => 'password',
            'Size' => '40',
            'Description' => 'Optional. Leave empty to use the token of the CubePath server in System Settings > Servers.',
        ),
        // configoption2
        'plan_name' => array(
            'FriendlyName' => 'Plan',
            'Type' => 'text',
            'Size' => '25',
            'Loader' => 'cubepath_LoadPlans',
            'SimpleMode' => true,
            'Description' => 'Location and operating system options for the plan are created when the product is saved.',
        ),
        // configoption3
        'project_id' => array(
            'FriendlyName' => 'Project',
            'Type' => 'text',
            'Size' => '10',
            'Loader' => 'cubepath_LoadProjects',
            'SimpleMode' => true,
            'Description' => 'CubePath project where VPS are created.',
        ),
    );
}

/**
 * Plan dropdown for the product module settings.
 *
 * @param array $params WHMCS module parameters of the product's server
 * @return array plan name => description
 */
function cubepath_LoadPlans($params)
{
    $catalog = \CubePath\WHMCS\Addon\Catalog::fetch(CubepathHelper::client($params));

    $plans = array();
    foreach ($catalog->plans() as $plan)
    {
        $plans[$plan['plan_name']] = sprintf(
            '%s ($%s/mo)%s',
            \CubePath\WHMCS\Addon\Catalog::describePlan($plan),
            number_format($plan['price_per_month'], 2),
            $plan['available'] ? '' : ' - out of stock'
        );
    }

    return $plans;
}

/**
 * Project dropdown for the product module settings.
 *
 * @param array $params WHMCS module parameters of the product's server
 * @return array project id => name
 */
function cubepath_LoadProjects($params)
{
    return \CubePath\WHMCS\Addon\Projects::names(CubepathHelper::client($params)->projects()->list());
}

/**
 * Test Connection button of System Settings > Servers.
 *
 * @param array $params WHMCS module parameters of the server
 * @return array
 */
function cubepath_TestConnection($params)
{
    try
    {
        CubepathHelper::client($params)->projects()->list();

        return array('success' => true, 'error' => '');
    }
    catch (\Exception $e)
    {
        return array('success' => false, 'error' => $e->getMessage());
    }
}

/**
 * Provision a new VPS.
 */
function cubepath_CreateAccount($params)
{
    $cubepath = new Cubepath($params);
    return cubepath_logResult($params, 'create', $cubepath->createAccount());
}

/**
 * Suspend the VPS (powers it off).
 */
function cubepath_SuspendAccount($params)
{
    $cubepath = new Cubepath($params);
    return cubepath_logResult($params, 'suspend', $cubepath->suspendAccount());
}

/**
 * Unsuspend the VPS (powers it on).
 */
function cubepath_UnsuspendAccount($params)
{
    $cubepath = new Cubepath($params);
    return cubepath_logResult($params, 'unsuspend', $cubepath->unsuspendAccount());
}

/**
 * Destroy the VPS and the firewall group the panel created for it.
 */
function cubepath_TerminateAccount($params)
{
    $cubepath = new Cubepath($params);
    $result = $cubepath->terminateAccount();
    if ($result === 'success')
    {
        try
        {
            PanelController::deleteFirewallGroup(CubepathHelper::client($params), (int)$params['serviceid']);
        }
        catch (\Exception $e)
        {
            logActivity('CubePath: could not clean up the firewall of service #' . (int)$params['serviceid'] . ': ' . $e->getMessage());
        }
    }

    return cubepath_logResult($params, 'terminate', $result);
}

/**
 * Resize the VPS to the product's plan.
 */
function cubepath_ChangePackage($params)
{
    $cubepath = new Cubepath($params);
    return cubepath_logResult($params, 'change_package', $cubepath->changePackage(), isset($params['configoption2']) ? $params['configoption2'] : null);
}

/**
 * Record a lifecycle action in the service's activity log.
 *
 * @param array       $params
 * @param string      $action
 * @param string      $result  'success' or an error message
 * @param string|null $details
 * @return string $result
 */
function cubepath_logResult($params, $action, $result, $details = null)
{
    $actor = isset($_SESSION['adminid']) && defined('ADMINAREA') ? 'admin' : 'system';
    ActivityHelper::log((int)$params['serviceid'], $actor, $action, $result === 'success', $result === 'success' ? $details : $result);

    return $result;
}

/**
 * Client area: the panel (ui/), and its JSON actions when the panel posts
 * back to this same page.
 */
function cubepath_ClientArea($params)
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cpaction']))
    {
        if (!CubepathHelper::validToken(isset($_POST['token']) ? $_POST['token'] : ''))
        {
            PanelController::sendJson(array('ok' => false, 'error' => 'Your session expired. Reload the page and try again.'));
        }
        PanelController::respond($params, PanelController::ACTOR_CLIENT, $_POST['cpaction'], isset($_POST['cpdata']) ? $_POST['cpdata'] : '{}');
    }

    if (in_array($params['status'], array('Terminated', 'Cancelled', 'Fraud'), true))
    {
        return '';
    }

    return array(
        'templatefile' => 'template/panel',
        'vars'         => array(
            'cubepathPanel' => CubepathHelper::panelHtml(array(
                'mode'      => 'client',
                'endpoint'  => CubepathHelper::systemUrl() . 'clientarea.php?action=productdetails&id=' . (int)$params['serviceid'],
                'token'     => generate_token('plain'),
                'lang'      => CubepathHelper::clientLanguage($params),
                'serviceId' => (int)$params['serviceid'],
            )),
        ),
    );
}

/**
 * Admin service page: the same panel, served through the CubePath addon.
 */
function cubepath_AdminServicesTabFields($params)
{
    return array(
        'CubePath' => CubepathHelper::panelHtml(array(
            'mode'      => 'admin',
            'endpoint'  => 'addonmodules.php?module=cubepath&cpapi=1',
            'token'     => generate_token('plain'),
            'lang'      => CubepathHelper::adminLanguage(),
            'serviceId' => (int)$params['serviceid'],
        )),
    );
}

/**
 * Power buttons on the admin service page.
 *
 * @param array $params WHMCS module parameters
 * @return array
 */
function cubepath_AdminCustomButtonArray($params)
{
    return array(
        'Start'  => 'start',
        'Reboot' => 'reboot',
        'Stop'   => 'stop',
    );
}

/**
 * Admin action: Start VPS.
 *
 * @param array $params WHMCS module parameters
 * @return string 'success' or error message
 */
function cubepath_start($params)
{
    $cubepath = new Cubepath($params);
    return cubepath_logResult($params, 'power_start', $cubepath->start());
}

/**
 * Admin action: Reboot VPS.
 *
 * @param array $params WHMCS module parameters
 * @return string 'success' or error message
 */
function cubepath_reboot($params)
{
    $cubepath = new Cubepath($params);
    return cubepath_logResult($params, 'power_reboot', $cubepath->reboot());
}

/**
 * Admin action: Stop VPS.
 *
 * @param array $params WHMCS module parameters
 * @return string 'success' or error message
 */
function cubepath_stop($params)
{
    $cubepath = new Cubepath($params);
    return cubepath_logResult($params, 'power_stop', $cubepath->stop());
}
