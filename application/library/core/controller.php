<?php
namespace core;

class controller extends \Yaf\Controller_Abstract {

	public function setBody($data, string $info='', int $code=200){
		$rev=\getBody($data, $info, $code);
		if(IS_CLI) {
			$response = $this->getResponse();
			$response->contentBody = $rev;
		}else{
			$rev['argv']=$this->getData();
			$rev['serv']=[
				'execTime'=>\exeTime(SYS_START_TIME),
				'runMem'=>\run_mem(SYS_MEMORY_USE)
			];
			r($rev);
		}
		return $rev;
	}
	public function setErr($e,$method,$code=500){
		$this->setBody($e->getMessage(), $method, $code);
	}
	public function getData(){
		try {
			$req = $this->getRequest();
			$raw = $req->getParams();
			if (IS_CLI) {
				return $raw;
			}

			$m = $req->getModuleName();
			$c = $req->getControllerName();
			$a = $req->getActionName();
			$f = APPLICATION_PATH . '/_data/' . $m . '/' . $c . '.php';
			$n = $raw['data'] ?? 'def';
			require_once($f);
			$data = \IndexData::getData($a, $n);
			return $data;
		}catch (\Exception $e){
			throw $e;
		}
	}
	public function getTaskId(){
		return $this->getRequest()->task_id;
	}

	public function getRedis($node){
		return \publics::getRedis($node);
	}
	public function getDB($node){
		return \publics::getDB($node);
	}
}