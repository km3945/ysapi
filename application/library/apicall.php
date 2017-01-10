<?php
class apicall{
	public $packType;
	public $keyNames;
	public $items;
	public $count;
	public $config;
	public $host;
	public $port;
	public $timeout;
	public $tackPid;
	private $defConfig = [
		'host'                  => '127.0.0.1',
		'port'                  => 9501,
		'timeout'               => 2,			//默认为0.1s，即100ms
		'pack_type'             => 'msgpack',	//系列化方法[msgpack | json]
		'package_max_length'    => 1024 * 640,	//协议最大长度 k
		'open_length_check'     => true,		//是否检查包长度
		'package_length_offset' => 0,			//第N个字节是包长度的值
		'package_body_offset'   => 5,			//第几个字节开始计算长度
		'package_length_type'   => 'NC'			//协议类型
	];

	public function __construct(array $config=[], string $taskPid='0'){
		$this->keyNames=[];
		$this->items=[];
		$this->count=0;
		$this->tackPid=$taskPid;
		$this->config=array_merge($this->defConfig, $config);
		$this->host=$this->config['host'];
		$this->port=intval($this->config['port']);
		$this->timeout=floatval($this->config['timeout']);
		$this->packType=$this->config['pack_type'];
		unset($this->config['host'],$this->config['port'],$this->config['timeout'],$this->config['pack_type']);
	}
	public function add(string $keyName, string $rout, $data=[]){
		$keyName=trim($keyName);
		if(!$keyName){
			throw new \Exception("keyName can not be null or empty: [{$rout}]");
		}
		if(is_numeric($keyName)){
			throw new \Exception("KeyName can not be a number: [{$keyName} - {$rout}]]");
		}
		if($keyName==='code' || $keyName==='serv'){
			throw new \Exception("KeyName not set 'code' or 'serv': [{$keyName} - {$rout}]");
		}
		if(isset($this->keyNames[$keyName])){
			throw new \Exception("The keyName already exists: [{$keyName} - {$rout}]");
		}
		$routs = $this->splitRout($rout);
		$routs['data']=$data;
		$this->items[$keyName]=$routs;
		$this->count++;
		$this->keyNames[$keyName]=1;
		return $this;
	}
	public function exec($source){
		try {
			if (!$this->count) {
				throw new \Exception('No data is executable');
			}
			$client = $this->getSwooleClient();
			$setups = [
				'source' => $source,
				'count'  => $this->count,
				'task_pid'=> $this->tackPid,
				'data'   => $this->items
			];
			$client->send($this->pack($setups));
			$res1 = @$client->recv();
			$client->close();
			$res = $this->pack($res1, true);
			$this->clean();
			return $res;
		}catch (\Exception $e){
			return [
				'code'=>500,
				'info'=>'client: '.$e->getMessage(),
				'serv'=>implode('|',[0,0,0,0,0])
			];
		}
	}
	public function clean(){
		$this->keyNames=[];
		$this->items=[];
		$this->count=0;
		$this->tackPid='0';
		return true;
	}
	public function getItems(){
		return $this->items;
	}
	public function getCount(){
		return $this->count;
	}
	public function getConfig(){
		return $this->config;
	}
	private function splitRout($rout){
		$exp=explode('/', $rout, 3);
		$tmp=['module','controller','action'];
		$cmd=[];
		foreach ($tmp as $i=>$k){
			$t=trim($exp[$i]);
			if(!$t){
				throw new \Exception("CMD param is empty: {$k} [{$rout}]");
			}
			$cmd[$k]=($i<2)?ucfirst($t):lcfirst($t);
		}
		return $cmd;
	}
	private function packFun($data,$isun=false){
		if($isun){
			if($this->packType==='json'){
				return json_decode($data, true);
			}else{
				return msgpack_unpack($data);
			}
		}else{
			if($this->packType==='json'){
				return json_encode($data, JSON_UNESCAPED_UNICODE);
			}else{
				return msgpack_pack($data);
			}
		}
	}
	private function pack($data, $isun=false){
		if(!$data){
			throw new \Exception('No data can be packaged: '.__METHOD__);
		}
		if($isun){
			['len'=>$len,'typ'=>$typ]=unpack("Nlen/Ctyp", $data);
			$msg = substr($data, -$len);
			return $this->packFun($msg,true);
		}

		$typ=intval($this->packType==='msgpack');
		$msg=$this->packFun($data);
		$end=pack("NC", strlen($msg),$typ).$msg;
		if(strlen($end)>$this->config['package_max_length']){
			throw new \Exception('The packet length exceeds the limit: '.__METHOD__);
		}
		return $end;
	}
	private function getSwooleClient(){
		$client = new Swoole\Client(SWOOLE_SOCK_TCP);
		$client->set($this->config);
		if (!$client->connect($this->host, $this->port, $this->timeout)) {
			throw new Exception("connect failed. Error: {$client->errCode}");
		}
		return $client;
	}
}