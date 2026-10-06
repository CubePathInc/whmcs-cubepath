<?php

class IsochangeController extends CubepathController
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
			// Handle POST actions
			if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']))
			{
				$action = filter_input(INPUT_POST, 'action');

				if ($action === 'mount' && !empty($_POST['iso_id']))
				{
					$isoId = filter_input(INPUT_POST, 'iso_id');
					$this->client->vps()->isos()->mount($vpsId, $isoId);
					SessionHelper::setFlashMessage('success', LangHelper::T('isochange.index.mount_success'));
					$this->redirect('Isochange', 'index');
				}

				if ($action === 'unmount')
				{
					$this->client->vps()->isos()->unmount($vpsId);
					SessionHelper::setFlashMessage('success', LangHelper::T('isochange.index.unmount_success'));
					$this->redirect('Isochange', 'index');
				}
			}

			$isoData = $this->client->vps()->isos()->list($vpsId);
			$isos = isset($isoData['items']) ? $isoData['items'] : (isset($isoData['isos']) ? $isoData['isos'] : $isoData);
			$mountedIsoId = isset($isoData['mounted_iso_id']) ? $isoData['mounted_iso_id'] : null;

			return array(
				'vars' => array(
					'isos' => $isos,
					'mountedIsoId' => $mountedIsoId,
				)
			);
		}
		catch (\Cubepath\APIError $e)
		{
			SessionHelper::setFlashMessage('danger', $e->getMessage());
			return;
		}
	}
}
