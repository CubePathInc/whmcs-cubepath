<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class SshkeysController extends CubepathController
{

	public function __construct($params)
	{
		parent::__construct($params);
	}

	public function indexAction()
	{
		if (!$this->client)
		{
			return array('error' => LangHelper::T('core.client.api_connection_error'));
		}

		try
		{
			$allowKeys = Capsule::table('cubepath_sshkeys')
				->where('client_id', $this->clientID)
				->get();

			if (empty($allowKeys) || count($allowKeys) == 0)
			{
				return array('vars' => array('keys' => array()));
			}

			$allowedIds = array();
			foreach ($allowKeys as $row)
			{
				$allowedIds[] = $row->ssh_key_id;
			}

			$allKeys = $this->client->sshKeys()->list();
			$keys = array();
			$keyList = isset($allKeys['ssh_keys']) ? $allKeys['ssh_keys'] : $allKeys;

			if (is_array($keyList))
			{
				foreach ($keyList as $key)
				{
					if (isset($key['id']) && in_array($key['id'], $allowedIds))
					{
						$keys[] = $key;
					}
				}
			}

			return array('vars' => array('keys' => $keys));
		}
		catch (\Cubepath\APIError $e)
		{
			SessionHelper::setFlashMessage('danger', $e->getMessage());
			return array('vars' => array('keys' => array()));
		}
	}

	public function addAction()
	{
		if (!$this->client)
		{
			return array('error' => LangHelper::T('core.client.api_connection_error'));
		}

		if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name']) && !empty($_POST['ssh_key']))
		{
			try
			{
				$name = CubepathHelper::cleanString($_POST['name']);
				$sshKey = $_POST['ssh_key'];

				$response = $this->client->sshKeys()->create($name, $sshKey);

				$keyId = isset($response['id']) ? $response['id'] : null;
				if ($keyId)
				{
					Capsule::table('cubepath_sshkeys')->insert(array(
						'client_id' => $this->clientID,
						'ssh_key_id' => $keyId,
					));
				}
				SessionHelper::setFlashMessage('success', LangHelper::T('sshkeys.add.success'));
			}
			catch (\Cubepath\APIError $e)
			{
				SessionHelper::setFlashMessage('danger', LangHelper::T('sshkeys.add.error') . ' ' . $e->getMessage());
			}
		}

		$this->redirect('Sshkeys', 'index');
	}

	public function deleteAction()
	{
		if (!$this->client)
		{
			return array('error' => LangHelper::T('core.client.api_connection_error'));
		}

		if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['key_id']))
		{
			$keyId = (int) $_POST['key_id'];

			$allow = Capsule::table('cubepath_sshkeys')
				->where('ssh_key_id', $keyId)
				->where('client_id', $this->clientID)
				->first();

			if (!empty($allow))
			{
				try
				{
					$this->client->sshKeys()->delete($keyId);
					Capsule::table('cubepath_sshkeys')
						->where('ssh_key_id', $keyId)
						->where('client_id', $this->clientID)
						->delete();
					SessionHelper::setFlashMessage('success', LangHelper::T('sshkeys.delete.success'));
				}
				catch (\Cubepath\APIError $e)
				{
					SessionHelper::setFlashMessage('danger', $e->getMessage());
				}
			}
			else
			{
				SessionHelper::setFlashMessage('danger', LangHelper::T('sshkeys.delete.error'));
			}
		}

		$this->redirect('Sshkeys', 'index');
	}
}
