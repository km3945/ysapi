<?php
try {
	define('IS_CLI', PHP_SAPI==='cli');
	if(!IS_CLI)exit();
	define('APPLICATION_NAME', 'api');
	define('APPLICATION_PATH', realpath(__DIR__));
	include(sprintf('%s/service/service_%s.php', APPLICATION_PATH, APPLICATION_NAME));

	$server = new server(APPLICATION_NAME);
	$server->run();
}catch (Exception $e){
	die('run-ERROR: '.$e->getMessage().PHP_EOL);
}