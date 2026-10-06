<?php

class FirewallController extends CubepathController
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

		$vpsId = (int) $this->getVpsId();

		try
		{
			$groups = $this->client->firewall()->list();
			$groupList = isset($groups['groups']) ? $groups['groups'] : $groups;

			// Get current VPS details to show assigned groups
			$vps = $this->client->vps()->get($vpsId);
			$assignedGroupIds = isset($vps['firewall_group_ids']) ? $vps['firewall_group_ids'] : array();

			return array(
				'vars' => array(
					'groups' => $groupList,
					'assignedGroupIds' => $assignedGroupIds,
					'vps' => $vps,
				)
			);
		}
		catch (\Cubepath\APIError $e)
		{
			SessionHelper::setFlashMessage('danger', $e->getMessage());
			return;
		}
	}

	public function assignAction()
	{
		if (!$this->client)
		{
			return array('error' => LangHelper::T('core.client.api_connection_error'));
		}

		$vpsId = (int) $this->getVpsId();

		if ($_SERVER['REQUEST_METHOD'] === 'POST')
		{
			try
			{
				$groupIds = isset($_POST['group_ids']) ? array_map('intval', $_POST['group_ids']) : array();
				$this->client->firewall()->assignToVPS($vpsId, $groupIds);
				SessionHelper::setFlashMessage('success', LangHelper::T('firewall.assign.success'));
			}
			catch (\Cubepath\APIError $e)
			{
				SessionHelper::setFlashMessage('danger', $e->getMessage());
			}
		}

		$this->redirect('Firewall', 'index');
	}
}
