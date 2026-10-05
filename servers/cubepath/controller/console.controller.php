<?php

class ConsoleController extends CubepathController
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
		return array(
			'vars' => array(
				'message' => LangHelper::T('console.index.not_available'),
			)
		);
	}
}
