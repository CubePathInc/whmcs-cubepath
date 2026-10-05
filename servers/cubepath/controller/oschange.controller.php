<?php

class OschangeController extends CubepathController
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
			// Handle POST for reinstall
			if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['template_name']))
			{
				$templateName = CubepathHelper::cleanString($_POST['template_name']);
				$this->client->vps()->reinstall($vpsId, $templateName);
				SessionHelper::setFlashMessage('warning', LangHelper::T('oschange.index.success'));
				$this->redirect('Main', 'index');
			}

			// Get available templates
			$templates = $this->client->vps()->templates();
			$vps = $this->client->vps()->get($vpsId);

			return array(
				'vars' => array(
					'templates' => $templates,
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
}
