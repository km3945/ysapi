<?php
use Yaf\Loader;

class Bootstrap extends \Yaf\Bootstrap_Abstract{

	public function _initConfig($dispatcher) {
		if(IS_CLI)return;
		$arrConfig = \Yaf\Application::app()->getConfig();
		\Yaf\Registry::set('config', $arrConfig);
	}
	public function _initView($dispatcher) {
		$dispatcher->disableView();
	}
	public function _initHelper()
	{
		if (!IS_CLI) {
			Loader::import('ref/ref.php');
			ref::config('expLvl', 2);
		}
		Loader::import('helper.php');
		Loader::import('resources.php');
	}
}