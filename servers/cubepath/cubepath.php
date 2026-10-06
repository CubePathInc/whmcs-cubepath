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
 * Provision a new CubePath VPS instance.
 *
 * @param array $params WHMCS module parameters
 * @return string 'success' or error message
 */
function cubepath_CreateAccount($params)
{
    $cubepath = new Cubepath($params);
    return $cubepath->createAccount();
}

/**
 * Suspend a CubePath VPS (powers off the server).
 *
 * @param array $params WHMCS module parameters
 * @return string 'success' or error message
 */
function cubepath_SuspendAccount($params)
{
    $cubepath = new Cubepath($params);
    return $cubepath->suspendAccount();
}

/**
 * Unsuspend a CubePath VPS (powers on the server).
 *
 * @param array $params WHMCS module parameters
 * @return string 'success' or error message
 */
function cubepath_UnsuspendAccount($params)
{
    $cubepath = new Cubepath($params);
    return $cubepath->unsuspendAccount();
}

/**
 * Terminate (destroy) a CubePath VPS.
 *
 * @param array $params WHMCS module parameters
 * @return string 'success' or error message
 */
function cubepath_TerminateAccount($params)
{
    $cubepath = new Cubepath($params);
    return $cubepath->terminateAccount();
}

/**
 * Handle plan upgrade/downgrade for a CubePath VPS.
 *
 * @param array $params WHMCS module parameters
 * @return string 'success' or error message
 */
function cubepath_ChangePackage($params)
{
    $cubepath = new Cubepath($params);
    return $cubepath->changePackage();
}

/**
 * Render the client area for the CubePath module.
 *
 * @param array $params WHMCS module parameters
 * @return array Template file and variables for WHMCS rendering
 */
function cubepath_ClientArea($params)
{
    $render = new CubepathRender($params);
    return $render->render('ClientArea');
}

/**
 * Define admin custom action buttons.
 *
 * @param array $params WHMCS module parameters
 * @return array Button labels mapped to function suffixes
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
    return $cubepath->start();
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
    return $cubepath->reboot();
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
    return $cubepath->stop();
}
