<?php
class server{
	public $host;
	public $port;
	public $sw;
	public $yaf;
	public $env;
	public $uname;
	public $packType; // 0=json, 1=msgpack
	public $packMaxLen;
	public $server_ip;
	public $res_mq_log;
	public $ids=[];

	public function __construct(string $uname, string $configFile='/conf/service.ini', string $env='product'){
		if (!defined('IS_CLI')) {
			throw new \Exception('no set IS_CLI');
		}
		if (!defined('APPLICATION_PATH')) {
			throw new \Exception('no set APPLICATION_PATH');
		}
		$this->uname = $uname;
		$this->env = $this->initEnv($env);
		$this->server_ip=current(swoole_get_local_ip());
		$config = $this->getConfig(APPLICATION_PATH . $configFile);
		$config['log_file']=sprintf($config['log_file'],$uname);
		$this->host = $config['host'];
		$this->port = (int)$config['port'];
		unset($config['host'], $config['port']);
		$config['package_max_length'] = intval($config['package_max_length']);
		$this->packMaxLen=$config['package_max_length'];
		$this->sw = new \Swoole\Server($this->host, $this->port);
		$this->sw->set($config);
		$this->bind($config);
	}
	public function run(){
		$this->sw->start();
	}
	public function onConnect(swoole_server $serv, $fd, $from_id){
		$this->ids[$fd]=dk_get_next_id(); // 这里看情况上否要用个定时器做清理
		$this->log(implode('|',[$this->ids[$fd],'onConnect',$fd,$from_id]));
	}
	public function onClose(swoole_server $serv, $fd, $from_id){
		$this->log(implode('|',[$this->ids[$fd],'onClose',$fd,$from_id]));
		unset($this->ids[$fd]);
	}
	public function onStart(swoole_server $serv){
		$this->log("->[onStart] PHP=".PHP_VERSION." Yaf=".\YAF\VERSION." swoole=".SWOOLE_VERSION." Master-Pid={$this->sw->master_pid} Manager-Pid={$this->sw->manager_pid}");
		swoole_set_process_name("php-{$this->uname}:master");
	}
	public function onReceive(swoole_server $serv, $fd, $from_id, $data){
		$start_time=microtime(true);
		$s_task_id=$this->ids[$fd];
		$this->log(implode('|',[$s_task_id,'onReceive',$fd,$from_id]));
		$task_pid='0';
		try {
			$_rev=$this->unpack($data);
			['source'=>$source,'count'=>$count,'data'=>$datas,'task_pid'=>$task_pid]=$_rev;
			foreach ($datas as &$_v){
				$_v['s_task_id']=$s_task_id;
			}

			$resv=$serv->taskWaitMulti($datas, 0.5); //def 0.5 //throw new \Exception('aaaaaaaaaaaaaaaaaaaa');
			$codes=0;
			$content=[];
			$keys=array_keys($datas);
			foreach($keys as $i=>$k){
				if(isset($resv[$i])) {
					$content[$k] = $resv[$i];
					$codes+=$resv[$i]['code'];
				}else{
					$content[$k] =\getBody('', 'timeout or other', 500);
					$content[$k]['serv']=implode('|',[0,$from_id,$from_id,$start_time,\exeTime($start_time)]);
					$codes+=500;
				}
			}
			if($codes==(200*$count)){
				$code=200;
			}elseif($codes==(500*$count)){
				$code=500;
			}else{
				$code=300;
			}
			$content['code']=$code;
			$content['serv']=implode('|',[$s_task_id,$this->packType,$source,$count,$fd,$from_id,$start_time,\exeTime($start_time)]);
			$serv->send($fd, $this->pack($content));
			unset($resv,$datas,$i,$k);
		}catch (\Exception $e){
			$content=[
				'code'=>500,
				'info'=>'server: '.$e->getMessage(),
				'serv'=>implode('|',[$s_task_id,$fd,$from_id,$start_time,\exeTime($start_time)])
			];
			$serv->send($fd, $this->pack($content));
		}finally{
			$client_inof=$serv->connection_info($fd,$from_id,true);
			$client_inof['server_ip']=$this->server_ip;
			// 这里处理日志
			//$serv->task(['cmd_count_log'=>1,'s_task_id'=>$s_task_id,'s_task_pid'=>$task_pid,'re'=>$_rev,'rn'=>$content,'client'=>$client_inof]);//统计
			unset($keys,$_rev,$content,$source,$count,$codes,$code,$_v,$client_inof,$s_task_id,$task_pid);
		}
	}
	public function onTask(swoole_server $serv, $task_id, $src_worker_id, $data){
		$start_time=microtime(true);
		$isCount=isset($data['cmd_count_log']);
		$s_task_id=$data['s_task_id'];
		$this->log(implode('|',[$s_task_id,'onTask'.($isCount?'-Count':''),$task_id,$src_worker_id]));
		$sinfo=implode('|',[$task_id,$serv->worker_id,$src_worker_id,$start_time,'%s']);
		try {
			if($isCount){ // 统计处理
				return $this->handleCounts($s_task_id,$data['s_task_pid'],$data['re'],$data['rn'],$data['client']);
			}
			$content=$this->runYaf($data);
			$content['serv']=sprintf($sinfo,\exeTime($start_time));
			$serv->finish($content);
		}catch (\Exception $e){
			$content=\getBody('', $e->getMessage(), 500);
			$content['serv']=sprintf($sinfo,\exeTime($start_time));
			$serv->finish($content);
		}
	}
	public function onFinish(swoole_server $serv, $task_id, $data){
		//$this->log(implode('|',[$this->task_id,'onFinish',$task_id]));
	}
	public function onWorkerStart($serv, $worker_id){
		$this->ids=[]; // reload时清空
		if ($serv->taskworker) {
			$type = 'task';
			$configs = $this->initYaf();
			// 这里处理DB, REDIS, MQ等常驻资源
			//$res = new resources($configs);
			//\Yaf\Registry::set('res_db_ubuntu', $res->getDB('ubuntu'));
			//\Yaf\Registry::set('res_redis_self', $res->getRedis('self'));
			//$this->res_mq_log=$res->getMQexchange('ubuntu','Eapilogs');
		} else {
			$type = 'work';
			$loader=\Yaf\Loader::getInstance(APPLICATION_PATH.'/application/library');
			$loader::import('helper.php');
		}
		swoole_set_process_name("php-{$this->uname}:{$type}:" . $worker_id);
		$this->log("->[onWorkerStart] Type: {$type} WorkerId: {$worker_id} WorkerPid: {$serv->worker_pid}");
	}
	public function onWorkerStop($serv, $worker_id){
		$this->log("->[onWorkerStop] WorkerId: {$worker_id}");
	}
	public function onWorkerError($serv, $worker_id, $worker_pid, $exit_code){
		$this->log("->[onWorkerError] WorkerId: {$worker_id} WorkerPid: {$worker_pid} ExitCode: {$exit_code}");
	}
	public function onManagerStart($serv){
		$this->log("->[onManagerStart]");
		swoole_set_process_name("php-{$this->uname}:manager");
	}
	public function onManagerStop($serv){
		$this->log('->[onManagerStop]');
	}
	public function onShutdown($serv){
		$this->log("->[onShutdown]");
	}
	public function onPipeMessage($serv, $from_worker_id, $message){
		$this->log("->[onPipeMessage] FromWorkerId: {$from_worker_id} Message:".strlen($message));
	}
	public function onPacket($serv, $data, $client_info){
		$this->log("->[onPacket]");
	}
	public function onTimer($serv, $interval){
		$this->log("->[onTimer]");
	}
	public function bind($config){
		$this->sw->on('Start',[$this,'onStart']);
		$this->sw->on('Close',[$this,'onClose']);
		$this->sw->on('Connect',[$this,'onConnect']);
		$this->sw->on('Receive',[$this,'onReceive']);
		$this->sw->on('Shutdown',[$this,'onShutdown']);
		$this->sw->on('WorkerStop',[$this,'onWorkerStop']);
		$this->sw->on('WorkerStart',[$this,'onWorkerStart']);
		$this->sw->on('WorkerError',[$this,'onWorkerError']);
		$this->sw->on('ManagerStop',[$this,'onManagerStop']);
		$this->sw->on('ManagerStart',[$this,'onManagerStart']);
		if(isset($config['task_worker_num']) && boolval($config['task_worker_num'])){
			$this->sw->on('Task',[$this,'onTask']);
			$this->sw->on('Finish',[$this,'onFinish']);
		}
	}
	public function getConfig($file, $isSelf=true){
		$config = new \Yaf\Config\Ini($file, $this->env);
		if($isSelf){
			return $config->get($this->uname)->toArray();
		}
		return $config;
	}
	public function runYaf(array $datas){
		['module'=>$module,'controller'=>$controller,'action'=>$action,'data'=>$data,'s_task_id'=>$taskId]=$datas;
		unset($datas);
		if(!is_array($data)){
			$data=[$data];
		}

		$request = new \Yaf\Request\Simple('CLI', $module, $controller, $action, $data);
		$request->task_id=$taskId;
		$response = $this->yaf->getDispatcher()->returnResponse(true)->dispatch($request);
		if (!@property_exists($response, 'contentBody') || !is_array($response->contentBody)) {
			throw new \Exception('Not set or not an array: $response->body');
		}
		return $response->contentBody;
	}
	public function initYaf(){
		$configs=$this->getConfig(APPLICATION_PATH.'/conf/application.ini',false);
		\Yaf\Registry::set('config', $configs);
		$this->yaf=new \Yaf\Application(['application'=>$configs->get('application')->toArray()]);
		$this->yaf->bootstrap();
		return $configs;
	}
	public function initEnv($env){
		$def ='product';
		$env = !$env ? get_cfg_var('yaf.environ') : $env;
		if(!$env){
			$env=$def;
		}
		$env=strtolower($env);
		if(in_array($env,['product','develop','test','beta'])){
			return $env;
		}
		return $def;
	}
	public function pack($data){
		try {
			if (!$data) {
				throw new \Exception('pack');
			}
			$typ = intval($this->packType === 'msgpack');
			$msg = $this->packFun($data);
			$end = pack("NC", strlen($msg), $typ) . $msg;
			if (strlen($end) > $this->packMaxLen) {
				throw new \Exception('pack');
			}
			return $end;
		}catch(\Exception $e){
			throw new \Exception('pack', 6001);
		}
	}
	public function unpack($data){
		try{
			if (!$data) {
				throw new \Exception('unpack');
			}

			$res=unpack("Nlen/Ctyp", $data);
			if(!$res){
				throw new \Exception('unpack');
			}
			$len=intval($res['len']);
			$typ=intval($res['typ']);
			$this->packType = $typ === 1 ? 'msgpack' : 'json';
			$msg = substr($data, -$len);
			$rev=$this->packFun($msg, true);
			$this->checkRev($rev);
			return $rev;
		} catch (\Exception $e){
			throw new \Exception('unpack',6000);
		}
	}
	private function checkRev($data){
		if(!$data){
			throw new \Exception('checkRev');
		}
		$arr=['source','count','task_pid','data'];
		foreach ($arr as $v){
			if(!isset($data[$v])){
				throw new \Exception('checkRev');
			}
		}

		if($data['count']!=count($data['data'])){
			throw new \Exception('checkRev');
		}

		$arr1=['module','controller','action','data'];
		foreach ($data['data'] as $key=>$item){
			foreach ($arr1 as $v){
				if(!isset($item[$v])){
					throw new \Exception('checkRev');
				}
			}
		}
		return true;
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
	private function handleCounts($s_task_id,$s_task_pid,$rev,$data,$client){
		$serv=explode('|', $data['serv']);
		if(!$rev){ //处理错误500情况
			$rev=[
				'source'=>'unknown',
				'count'=>0
			];
			$data=[
				'code'=>500
			];
			$serv=[$serv[0],'msgpack',0,0,0,0,$serv[3],$serv[4]];
		}
		$rs=[
			'task_id'=>$s_task_id,
			'task_pid'=>$s_task_pid, //用来处理子请求,之后可以用这个看全部请求链
			'source'=>$rev['source'],
			'count'=>$rev['count'],
			'code'=>$data['code'],
			'serv_pack'=>$serv[1],
			'serv_work_wid'=>$serv[5],
			'serv_exec_time'=>$serv[7],
			'serv_start_time'=>$serv[6],
			'client'=>$client,
			'items'=>[]
		];
		unset($data['code'],$data['serv']);
		foreach ($data as $k=>$v){
			$tmp = explode('|',$v['serv']);
			$rou = $rev['data'][$k];
			$rs['items'][]=[
				'var_name'=>$k,
				'code'=>$v['code'],
				'info'=>$v['info'],
				'serv_task_wid'=>$tmp[1],
				'serv_work_wid'=>$tmp[2],
				'serv_exec_time'=>$tmp[4],
				'serv_start_time'=>$tmp[3],
				'serv_rout_module'=>$rou['module'],
				'serv_rout_controller'=>$rou['controller'],
				'serv_rout_action'=>$rou['action'],
				'serv_rout_data'=>json_encode((array)$rou['data'],JSON_UNESCAPED_UNICODE)
			];
		}
//		echo (print_r($rs, 1));
		//send MQ
		$routing_key='';
		try {
			$this->res_mq_log->publish(msgpack_pack($rs), $routing_key, AMQP_NOPARAM, ['delivery_mode' => 2]);    //消息持久化，在投递时指定 delivery_mode => 2（1 是非持久化）
		}catch (\Exception $e){
			$this->log(implode('|',[$s_task_id,'sendMqLog-Error',$e->getMessage()]));
		}
		return true;
	}
	public function log($msg,$control=0){
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
				error_log($msg,3,"/var/log/php-{$this->uname}.log");
				break;
			default:
				echo $msg;
		}
	}
}