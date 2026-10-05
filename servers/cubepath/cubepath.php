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
    );
}

/**
 * Product configuration options displayed in the WHMCS admin product setup.
 *
 * @param array $params WHMCS module parameters
 * @return array Configuration field definitions
 */
function cubepath_ConfigOptions($params)
{
    $productId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

    $configArray = array();

    // Config Option 1: API Token
    $configArray['api_token'] = array(
        'FriendlyName' => 'API Token',
        'Type' => 'text',
        'Size' => '40',
        'Description' => 'Your CubePath Cloud API token',
    );

    $apiToken = CubepathHelper::getProductConfigOption($productId, 'configoption1');

    if ($apiToken)
    {
        try
        {
            $client = new \Cubepath\CubepathClient($apiToken);
            // Validate connection by listing projects
            $client->get('/projects/');

            // Config Option 2: Plan Name
            $configArray['plan_name'] = array(
                'FriendlyName' => 'Plan Name',
                'Type' => 'text',
                'Size' => '25',
                'Description' => 'CubePath VPS plan name (e.g., rz.nano)',
            );

            // Config Option 3: Project ID
            $configArray['project_id'] = array(
                'FriendlyName' => 'Project ID',
                'Type' => 'text',
                'Size' => '10',
                'Description' => 'Default CubePath project ID (can be overridden per service via custom field)',
            );
        }
        catch (\Exception $e)
        {
            $configArray['api_token']['Description'] = 'API connection failed: ' . $e->getMessage();
        }
    }

    return $configArray;
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
