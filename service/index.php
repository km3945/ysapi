<?php
header('Content-type: text/html; charset=utf-8');		// 发送编码
define('SYS_START_TIME', microtime(true));				// 启始时间
define('SYS_MEMORY_USE', memory_get_usage());			// 启始内存
define('SYS_TIME', (isset($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time()));
define('BR', (PHP_SAPI==='cli' ? PHP_EOL : '<br />' . PHP_EOL));
define('IS_CLI', PHP_SAPI==='cli');
define('APPLICATION_PATH', realpath(__DIR__.'/../'));

// 调试函数
function dd($val = null){echo('<pre>' . htmlspecialchars(print_r($val, true)) . '</pre>'), BR;}
function ddd($val = null){die('<pre>' . htmlspecialchars(print_r($val, true)) . '</pre>');}
function NOW($style='Y-m-d H:i:s',$time=SYS_TIME){return date($style,$time);}

$application=new \Yaf\Application(APPLICATION_PATH . "/conf/application.ini");
$application->bootstrap()->run();