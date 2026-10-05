<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class DnsController extends CubepathController
{

	public function __construct($params)
	{
		parent::__construct($params);
		if (empty($this->getVpsId()))
		{
			SessionHelper::setFlashMessage('danger', LangHelper::T('core.client.create_vm_first'));
			$this->redirect('Main', 'index');
		}
	}

	public function indexAction()
	{
		if (!$this->client)
		{
			return array('error' => LangHelper::T('core.client.api_connection_error'));
		}

		try
		{
			$zonesQuery = Capsule::table('cubepath_dns')
				->where('client_id', $this->clientID)
				->get();

			$zones = array();
			foreach ($zonesQuery as $zoneRow)
			{
				try
				{
					$zoneDetails = $this->client->dns()->getZone($zoneRow->zone_uuid);
					$zoneDetails['_zone_uuid'] = $zoneRow->zone_uuid;
					$zoneDetails['_domain'] = $zoneRow->domain;
					$zones[] = $zoneDetails;
				}
				catch (\Cubepath\APIError $e)
				{
					// Zone may have been deleted externally; skip it
				}
			}

			return array(
				'vars' => array(
					'zones' => $zones,
				)
			);
		}
		catch (\Cubepath\APIError $e)
		{
			SessionHelper::setFlashMessage('danger', $e->getMessage());
			return;
		}
	}

	public function createAction()
	{
		if (!$this->client)
		{
			return array('error' => LangHelper::T('core.client.api_connection_error'));
		}

		if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['domain']))
		{
			try
			{
				$domain = CubepathHelper::cleanString($_POST['domain']);

				$params = array('domain' => $domain);

				$projectId = CubepathHelper::getCustomFieldValue($this->serviceID, 'project_id');
				if (!empty($projectId))
				{
					$params['project_id'] = (int) $projectId;
				}

				$zone = $this->client->dns()->createZone($params);

				$zoneUUID = isset($zone['uuid']) ? $zone['uuid'] : (isset($zone['zone_uuid']) ? $zone['zone_uuid'] : '');
				if (!empty($zoneUUID))
				{
					Capsule::table('cubepath_dns')->insert(array(
						'client_id' => $this->clientID,
						'service_id' => $this->serviceID,
						'zone_uuid' => $zoneUUID,
						'domain' => $domain,
					));
				}

				SessionHelper::setFlashMessage('success', LangHelper::T('dns.create.success'));
			}
			catch (\Cubepath\APIError $e)
			{
				SessionHelper::setFlashMessage('danger', $e->getMessage());
			}
		}

		$this->redirect('Dns', 'index');
	}

	public function manageAction()
	{
		if (!$this->client)
		{
			return array('error' => LangHelper::T('core.client.api_connection_error'));
		}

		$zoneUUID = filter_input(INPUT_GET, 'zone_uuid');
		if (empty($zoneUUID))
		{
			SessionHelper::setFlashMessage('danger', LangHelper::T('dns.manage.error'));
			$this->redirect('Dns', 'index');
		}

		// Verify the zone belongs to this client
		$allow = Capsule::table('cubepath_dns')
			->where('zone_uuid', $zoneUUID)
			->where('client_id', $this->clientID)
			->first();

		if (empty($allow))
		{
			SessionHelper::setFlashMessage('danger', LangHelper::T('dns.manage.error'));
			$this->redirect('Dns', 'index');
		}

		try
		{
			// Handle POST actions for record CRUD
			if ($_SERVER['REQUEST_METHOD'] === 'POST')
			{
				$redirectUrl = 'clientarea.php?action=productdetails&id=' . $this->serviceID
					. '&cloudController=Dns&cloudAction=manage&zone_uuid=' . urlencode($zoneUUID);

				if (isset($_POST['create_record']))
				{
					$recordParams = array(
						'name' => filter_input(INPUT_POST, 'name'),
						'type' => filter_input(INPUT_POST, 'type'),
						'content' => filter_input(INPUT_POST, 'content'),
						'ttl' => (int) filter_input(INPUT_POST, 'ttl'),
					);
					$type = $recordParams['type'];
					if (in_array($type, array('MX', 'SRV')))
					{
						$recordParams['priority'] = (int) filter_input(INPUT_POST, 'priority');
					}
					$this->client->dns()->createRecord($zoneUUID, $recordParams);
					SessionHelper::setFlashMessage('success', LangHelper::T('dns.manage.add_success'));
					header('Location: ' . $redirectUrl);
					die();
				}

				if (isset($_POST['update_record']) && !empty($_POST['record_uuid']))
				{
					$recordUUID = filter_input(INPUT_POST, 'record_uuid');
					$updateParams = array(
						'name' => filter_input(INPUT_POST, 'name'),
						'type' => filter_input(INPUT_POST, 'type'),
						'content' => filter_input(INPUT_POST, 'content'),
						'ttl' => (int) filter_input(INPUT_POST, 'ttl'),
					);
					if (isset($_POST['priority']))
					{
						$updateParams['priority'] = (int) filter_input(INPUT_POST, 'priority');
					}
					$this->client->dns()->updateRecord($zoneUUID, $recordUUID, $updateParams);
					SessionHelper::setFlashMessage('success', LangHelper::T('dns.manage.update_success'));
					header('Location: ' . $redirectUrl);
					die();
				}

				if (isset($_POST['delete_record']) && !empty($_POST['record_uuid']))
				{
					$recordUUID = filter_input(INPUT_POST, 'record_uuid');
					$this->client->dns()->deleteRecord($zoneUUID, $recordUUID);
					SessionHelper::setFlashMessage('success', LangHelper::T('dns.manage.delete_success'));
					header('Location: ' . $redirectUrl);
					die();
				}
			}

			$records = $this->client->dns()->listRecords($zoneUUID);
			$zone = $this->client->dns()->getZone($zoneUUID);

			return array(
				'vars' => array(
					'records' => isset($records['records']) ? $records['records'] : $records,
					'zone' => $zone,
					'zone_uuid' => $zoneUUID,
					'domain' => $allow->domain,
				)
			);
		}
		catch (\Cubepath\APIError $e)
		{
			SessionHelper::setFlashMessage('danger', $e->getMessage());
			$this->redirect('Dns', 'index');
		}
	}

	public function deleteAction()
	{
		if (!$this->client)
		{
			return array('error' => LangHelper::T('core.client.api_connection_error'));
		}

		if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['zone_uuid']))
		{
			$zoneUUID = filter_input(INPUT_POST, 'zone_uuid');

			$allow = Capsule::table('cubepath_dns')
				->where('zone_uuid', $zoneUUID)
				->where('client_id', $this->clientID)
				->first();

			if (!empty($allow))
			{
				try
				{
					$this->client->dns()->deleteZone($zoneUUID);
					Capsule::table('cubepath_dns')->where('zone_uuid', $zoneUUID)->delete();
					SessionHelper::setFlashMessage('success', LangHelper::T('dns.delete.success'));
				}
				catch (\Cubepath\APIError $e)
				{
					SessionHelper::setFlashMessage('danger', $e->getMessage());
				}
			}
			else
			{
				SessionHelper::setFlashMessage('danger', LangHelper::T('dns.delete.error'));
			}
		}

		$this->redirect('Dns', 'index');
	}
}
