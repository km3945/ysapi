<?php
/*
php api.php |awk '{sum+=$1} END {print "Avg= ", sum/NR}'
seq 50|xargs -i php /wwwroot/data_site/api.local.com/call.php
*/

define('APPLICATION_PATH', realpath(__DIR__));
$loader=\Yaf\Loader::getInstance(APPLICATION_PATH.'/application/library');
$loader::import('apicall.php');

for ($i = 0; $i < 1; $i++) {
	$process = new swoole_process(function(swoole_process $worker) {
		try {
			$sTime=microtime(true);
			$api = new apicall();
			$api->add('pagelist','index/index/index',['page'=>1]);
			$api->add('user','index/index/index2',['ud'=>1]);
			$api->add('mess','index/index/index3',['id'=>1]);
			$rs=$api->exec('www');
			$code=$rs['code'];
			if($code!=200){
				if($code==500){
					// 全错
				}elseif($code==300){
					// 部份错
				}else{
					// 异常
				}
			}

			$endTime=run_time($sTime);
			if(1) {
				logs(print_r($rs,1));
				$code=$code>200?$code.'----------------':$code;
				logs($endTime.' '.$code.' '.$rs['serv']);
			}else{
				logs($endTime);
			}
		}catch (Exception $e){
			echo $e->getMessage().PHP_EOL;
			die('ERROR-------------------------------'.PHP_EOL);
		}
	});
	$process->start();
}

function logs($msg,$control=0){
	if($control!==4 && defined('DEBUG_CODE')){
		if(DEBUG_CODE==0){ // 关闭
			return ;
		}
		if(DEBUG_CODE===3){ // 强制输出到日志文件
			$control=3;
		}
	}

	$msg=$msg.PHP_EOL;
	switch($control){
		case 0:
			echo $msg;
			break;
		case 1:
		case 2:
			die($msg);
			break;
		case 3:
		case 4:
			echo $msg;
			error_log($msg,3,'./log_'.basename(__FILE__).'.log');
			break;
		default:
			echo $msg;
	}
}

function run_time($stime = '')
{
	if ($stime == '') {
		if (!defined('SYS_START_TIME')) {
			return 0;
		}
		$stime = SYS_START_TIME;
	}

	$time = microtime(true) - $stime;

	if ($time > 0) {
		return number_format($time, 4);
	}
	return 0;
}