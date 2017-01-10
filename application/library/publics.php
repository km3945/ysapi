<?php
class publics {
	public static function getDB($node){
		if(IS_CLI) {
			$key = 'res_db_' . $node;
			return self::getResObject($key);
		}
		$res = new resources(\Yaf\Registry::get('config'));
		return $res->getDB($node);
	}
	public static function getRedis($node){
		if(IS_CLI) {
			$key = 'res_redis_' . $node;
			return self::getResObject($key);
		}
		$res = new resources(\Yaf\Registry::get('config'));
		return $res->getRedis($node);
	}
	private static function getResObject($key){
		if(!\Yaf\Registry::has($key)){
			throw new \Exception('no Resources Object: '.$key);
		}
		return \Yaf\Registry::get($key);
	}
}