<?php
class IndexData{
	public static $indexAction=[
		'def'=>[
			'page'=>1
		],
		'p1'=>[
			'page'=>1
		],
		'p2'=>[
			'page'=>2
		],
	];

	public static $index2Action=[
		'def'=>[
			'id'=>1
		],
		'u1'=>[
			'id'=>1
		],
		'u2'=>[
			'id'=>2
		],
	];

	public static $index3Action=[
		'def'=>[
			'id'=>1
		],
		'm1'=>[
			'id'=>1
		],
		'm2'=>[
			'id'=>2
		],
	];

	public static function getData($action, $node='def'){
		$action=$action.'Action';
		if(!isset(self::$$action)){
			return [];
		}
		$raw=self::$$action;
		if(!isset($raw[$node])){
			return [];
		}
		$rs=$raw[$node];
		if(!is_array($rs)){
			return [$rs];
		}
		return $rs;
	}
}