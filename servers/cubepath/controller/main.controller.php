<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class MainController extends CubepathController
{

	public function __construct($params)
	{
		parent::__construct($params);
	}

	public function indexAction()
	{
		if ($this->params['status'] == 'Pending')
		{
			return;
		}

		$vpsId = $this->getVpsId();

		if (empty($vpsId))
		{
			SessionHelper::setFlashMessage('warning', LangHelper::T('core.client.create_vm_first'));
			return;
		}

		if (!$this->client)
		{
			SessionHelper::setFlashMessage('danger', LangHelper::T('core.client.api_connection_error'));
			return;
		}

		try
		{
			// Handle POST actions
			if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']))
			{
				$action = filter_input(INPUT_POST, 'action');

				switch ($action)
				{
					case 'start':
						CubepathHelper::power($this->client, $vpsId, 'start');
						SessionHelper::setFlashMessage('success', LangHelper::T('main.index.power_start_success'));
						$this->redirect('Main', 'index');
						break;

					case 'stop':
						CubepathHelper::power($this->client, $vpsId, 'stop');
						SessionHelper::setFlashMessage('success', LangHelper::T('main.index.power_stop_success'));
						$this->redirect('Main', 'index');
						break;

					case 'reboot':
						CubepathHelper::power($this->client, $vpsId, 'reboot');
						SessionHelper::setFlashMessage('success', LangHelper::T('main.index.power_reboot_success'));
						$this->redirect('Main', 'index');
						break;

					case 'update_label':
						$label = strip_tags(filter_input(INPUT_POST, 'label'));
						if (!empty($label))
						{
							$this->client->vps()->update((int) $vpsId, array('label' => $label));
							SessionHelper::setFlashMessage('success', LangHelper::T('main.index.label_success'));
						}
						else
						{
							SessionHelper::setFlashMessage('danger', LangHelper::T('main.index.label_error'));
						}
						$this->redirect('Main', 'index');
						break;

					case 'change_password':
						$password = $_POST['password'];
						if (!empty($password) && strlen($password) >= 8)
						{
							$this->client->vps()->changePassword((int) $vpsId, $password);
							SessionHelper::setFlashMessage('success', LangHelper::T('main.index.password_success'));
						}
						else
						{
							SessionHelper::setFlashMessage('danger', LangHelper::T('main.index.password_error'));
						}
						$this->redirect('Main', 'index');
						break;
				}
			}

			// Get VPS details
			$vps = $this->client->vps()->get((int) $vpsId);
			$vps['ip_address'] = CubepathHelper::primaryIpv4($vps);

			return array(
				'vars' => array(
					'vps' => $vps,
				)
			);
		}
		catch (\Cubepath\APIError $e)
		{
			SessionHelper::setFlashMessage('danger', $e->getMessage());
			return;
		}
		catch (\RuntimeException $e)
		{
			SessionHelper::setFlashMessage('warning', LangHelper::T('main.index.vm_not_found'));
			return;
		}
	}
}
