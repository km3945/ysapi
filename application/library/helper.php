<?php
function getBody($data, string $info='', int $code=200){
	return [
		'code'=>(int)$code,
		'info'=>(string)$info,
		'data'=>$data
	];
}

function exeTime($stime){
	$time = microtime(true) - $stime;
	if ($time > 0) {
		return number_format($time, 4);
	}
	return 0;
}
function run_mem($smem) {
	$smem = array_sum(explode(' ', $smem));
	$emem = array_sum(explode(' ', memory_get_usage()));
	return number_format(($emem - $smem) / 1024) . 'kb';
}

function sfind($str, $findme, $tag = ','){
	return !(strpos($tag . $str . $tag, $tag . $findme . $tag) === false);
}

function sysinfo($isDie = false){
	$info = array(
		'OS' => PHP_OS,
		'PhpType' => php_sapi_name(),
		'PhpVersion' => PHP_VERSION,
		'uploadSize' => ini_get('upload_max_filesize'),
		'execTime' => ini_get('max_execution_time') . '秒',
		'ServerTime' => date("Y-n-j H:i:s"),
		'LocalTime' => gmdate("Y-n-j H:i:s", time() + 8 * 3600),
		'LastSpace' => round((@disk_free_space(".") / (1024 * 1024)), 2) . 'M',
		'register_globals' => get_cfg_var("register_globals") == "1" ? "ON" : "OFF",
		'magic_quotes_gpc' => (1 === get_magic_quotes_gpc()) ? 'YES' : 'NO',
		'magic_quotes_runtime' => (1 === get_magic_quotes_runtime()) ? 'YES' : 'NO',
	);
	if ($isDie) {
		die((print_r($info, true)));
	}
	return $info;
}