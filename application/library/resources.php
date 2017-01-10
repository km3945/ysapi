<?php
class resources{
	public $conf;

	public function __construct($config){
		$this->conf=$config;
	}
	public function getConfNode($node){
		$rs=$this->conf->get($node)->toArray();
		return $rs;
	}
	public function getDB($node){
		$conf=$this->getConfNode('db.'.$node);
		$conf['option'] = [
			PDO::NULL_TO_STRING => true,
			PDO::ATTR_CASE => PDO::CASE_NATURAL,
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
		];
		if(IS_CLI){
			$conf['option'][PDO::ATTR_PERSISTENT]=true;
		}
		return new \medoo($conf);
	}
	public function getRedis($node){
		$conf=$this->getConfNode('redis.'.$node);
		//$redis = new \Redis();
		//$redis->pconnect($conf['host'], intval($conf['port']), floatval($conf['timeout']));
		$redis=new selfRedis($conf);
		return $redis;
	}

	public function getMQexchange($node,$exchangeName){
		$channel = $this->getMQchannel($node);
		$exchange = new AMQPExchange($channel);
		$exchange->setName($exchangeName);
		return $exchange;
	}

	public function getMQqueue($node, $exchangeName, $queueName){
		$channel = $this->getMQchannel($node);
		$exchange = new AMQPExchange($channel);
		$exchange->setName($exchangeName);

		$queue = new AMQPQueue($channel);
		$queue->setName($queueName);
		return $queue;
	}

	private function getMQchannel($node){
		$conf=$this->getConfNode('mq.'.$node);
		$connection = new AMQPConnection($conf);
		$connection->connect();

		$channel = new AMQPChannel($connection);
		$channel->setPrefetchCount(1);
		return $channel;
	}
}


class selfRedis
{
	public $host;
	public $port;
	public $timeout;
	public $_redis;

	public function __construct($config){
		$this->host = $config['host'];
		$this->port = intval($config['port']);
		$this->timeout = floatval($config['timeout']);
		$this->connect();
	}
	public function connect()
	{
		try {
			if ($this->_redis) {
				unset($this->_redis);
			}
			$this->_redis = new \Redis();
			$this->_redis->pconnect($this->host, $this->port, $this->timeout);
		} catch (\RedisException $e) {
			throw new \Exception($e->getMessage());
		}
	}
	public function __call($method, $args = array())
	{
		$reConnect = false;
		while (1) {
			try {
				$result = call_user_func_array(array($this->_redis, $method), $args);
			} catch (\RedisException $e) {
				if ($reConnect) {
					throw new \Exception($e->getMessage());
				}
				$this->_redis->close();
				$this->connect();
				$reConnect = true;
				continue;
			}
			return $result;
		}
		return false; // 不可能走到这句,为毛我要写?
	}
}