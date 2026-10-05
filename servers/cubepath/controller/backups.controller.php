<?php

class BackupsController extends CubepathController
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
			$backups = $this->client->vps()->backups()->list($vpsId);
			$settings = $this->client->vps()->backups()->getSettings($vpsId);

			return array(
				'vars' => array(
					'backups' => isset($backups['backups']) ? $backups['backups'] : $backups,
					'settings' => $settings,
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

		$vpsId = (int) $this->getVpsId();

		if ($_SERVER['REQUEST_METHOD'] === 'POST')
		{
			try
			{
				$notes = isset($_POST['notes']) ? CubepathHelper::cleanString($_POST['notes']) : null;
				$this->client->vps()->backups()->create($vpsId, $notes);
				SessionHelper::setFlashMessage('success', LangHelper::T('backups.create.success'));
			}
			catch (\Cubepath\APIError $e)
			{
				SessionHelper::setFlashMessage('danger', $e->getMessage());
			}
		}

		$this->redirect('Backups', 'index');
	}

	public function restoreAction()
	{
		if (!$this->client)
		{
			return array('error' => LangHelper::T('core.client.api_connection_error'));
		}

		$vpsId = (int) $this->getVpsId();

		if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['backup_id']))
		{
			try
			{
				$backupId = (int) $_POST['backup_id'];
				$this->client->vps()->backups()->restore($vpsId, $backupId);
				SessionHelper::setFlashMessage('success', LangHelper::T('backups.restore.success'));
			}
			catch (\Cubepath\APIError $e)
			{
				SessionHelper::setFlashMessage('danger', $e->getMessage());
			}
		}

		$this->redirect('Backups', 'index');
	}

	public function deleteAction()
	{
		if (!$this->client)
		{
			return array('error' => LangHelper::T('core.client.api_connection_error'));
		}

		$vpsId = (int) $this->getVpsId();

		if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['backup_id']))
		{
			try
			{
				$backupId = (int) $_POST['backup_id'];
				$this->client->vps()->backups()->delete($vpsId, $backupId);
				SessionHelper::setFlashMessage('success', LangHelper::T('backups.delete.success'));
			}
			catch (\Cubepath\APIError $e)
			{
				SessionHelper::setFlashMessage('danger', $e->getMessage());
			}
		}

		$this->redirect('Backups', 'index');
	}

	public function settingsAction()
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
				$settingsParams = array(
					'enabled' => isset($_POST['enabled']) ? (bool) $_POST['enabled'] : false,
					'schedule_hour' => isset($_POST['schedule_hour']) ? (int) $_POST['schedule_hour'] : 0,
					'retention_days' => isset($_POST['retention_days']) ? (int) $_POST['retention_days'] : 1,
					'max_backups' => isset($_POST['max_backups']) ? (int) $_POST['max_backups'] : 1,
				);
				$this->client->vps()->backups()->updateSettings($vpsId, $settingsParams);
				SessionHelper::setFlashMessage('success', LangHelper::T('backups.settings.success'));
			}
			catch (\Cubepath\APIError $e)
			{
				SessionHelper::setFlashMessage('danger', $e->getMessage());
			}
		}

		$this->redirect('Backups', 'index');
	}
}
