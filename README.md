ysapi
======

## 简介
* ysapi是一个由 swoole + yaf 实现socket服务基础框架.
* 由swoole实现socket服务,对外提供API接口, yaf负责api对应的业务逻辑.

### 功能介绍
* 基于socket提供更快的数据返回
* 基于swoole多进程task模型,实现多任务并行处理
* 客户端单次调用,服务端自动拆分任务给多个task进程并发处理,并一次返回给客户端
* 每次调用生成唯一ID,此ID可把当次所有请求,任务串联起来,依此分析程序问题
* DB, REDIS, MQ均长连接常驻,减少网络IO
* 基于yaf,提供可靠,快速,简单的业务开发
* 基于MQ异步收集请求日志(这个是可选的)

### 主要解决的问题
* 基于swoole开发servers时,每次修改业务代码,或调试,都要重启整个服务或reload,才能看到调试信息或结果
* 这就使得开发很难受,很影响工作效率.
* 那能不能像传统开发一样,用浏览器来调试呢?
* 每次只用修改->保存->刷新浏览器就能看到调试信息和结果,和以往的工作方式一样.
* 答案是肯定的.
* 基于yaf的特点,很方便的实现.
* 当业务逻辑实现以后,只要发布或重启servers即可.

## 安装
### 必要的扩展
* nginx
* mysql 5.7
* PHP 7.1
* extension=/usr/local/php7/lib/php/extensions/no-debug-non-zts-20160303/yaf.so
* extension=/usr/local/php7/lib/php/extensions/no-debug-non-zts-20160303/yaconf.so
* extension=/usr/local/php7/lib/php/extensions/no-debug-non-zts-20160303/swoole.so
* extension=/usr/local/php7/lib/php/extensions/no-debug-non-zts-20160303/msgpack.so
* extension=/usr/local/php7/lib/php/extensions/no-debug-non-zts-20160303/amqp.so
* extension=/usr/local/php7/lib/php/extensions/no-debug-non-zts-20160303/igbinary.so
* extension=/usr/local/php7/lib/php/extensions/no-debug-non-zts-20160303/redis.so
* extension=/usr/local/php7/lib/php/extensions/no-debug-non-zts-20160303/donkeyid.so

#### php.ini扩展配置
```conf
[DonkeyId]
;0-4095
donkeyid.node_id=0
;0-Timestamp
donkeyid.epoch=0

[yaconf]
yaconf.directory=/tmp/yaconf
; yaconf.check_delay=0

[yaf]
yaf.environ = product           ; develop test
yaf.use_namespace = 1
; yaf.action_prefer = 0
; yaf.lowcase_path = 0
; yaf.library = NULL
; yaf.cache_config = 0
; yaf.name_suffix = 1
; yaf.name_separator = ""
; yaf.forward_limit = 5
; yaf.use_spl_autoload = 0
```

#### 代码安装
把文件放到
```shell
/wwwroot/data_site/ysapi
```

#### nginx.conf配置
```conf
     server {
        listen       80;
        server_name  api.local.com;
        index index.html index.htm index.php;
        root /wwwroot/data_site/ysapi/service;

        if (!-e $request_filename) {
                rewrite ^/(.*)  /index.php/$1 last;
        }

        location / {
                try_files $uri $uri/ /index.php?$query_string;
        }

        location ~ \.php$ {
                fastcgi_pass  127.0.0.1:9000;
                fastcgi_index index.php;
                fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
                include       fastcgi_params;
        }
    }
```

* 重启Nginx,修改本机host文件
```shell
127.0.0.1 api.local.com
```

### 浏览器访问
* 保存后,重启浏览器打开(以下是yaf默认路由方式):
```
http://api.local.com/index/index/index/data/def

http://api.local.com/ index/ index/   index/ data/def
                域名/  模块/ 控制器/  方法 / 参数/值
```
若无问题,将看到:
![web](https://github.com/km3945/ysapi/blob/master/doc/web1.png)

之后就可以按yaf的方式开发API业务逻辑


### 启动 servers
业务代码开发完成后,我们可以reload或重启servers,来提供最新的接口
```shell
php /wwwroot/data_site/ysapi/run.php
```
服务启动无异常,可以使用api调用方法来尝试调用:
```shell
php /wwwroot/data_site/ysapi/call.php
```

### 客户端接口调用代码用例
* 用例中我们同时调用3个接口获得不同的数据
```php
try {
    $api = new apicall();
    $api->add('pagelist','index/index/index',['page'=>1]);
    $api->add('user','index/index/index2',['user'=>1]);
    $api->add('mess','index/index/index3',['mess'=>1]);
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

    $pagelist=$rs['pagelist'];
    $user=$rs['user'];
    $mess=$rs['mess'];

    echo(print_r($pagelist,1));
}catch (Exception $e){
	echo $e->getMessage().PHP_EOL;
	die('ERROR-------------------------------'.PHP_EOL);
}
```

### 业务开发
* 按照YAF的方式去开发接口业务逻辑
* 新建模块->建控制器->建方法->浏览器访问你的方法进行调试
* 方法传入的参数可到/data/模块/控制器文件中定义,如:
```php
// /yafapi/data/Index/Index.php
// 路径及名字按YAF的方式定义
class IndexData{
	public static $index3Action=[  //名字引方法名对应,键名是你要引用的节点名,默认def
		'def'=>[
			'sql'=>'select * from apilogs ORDER BY RAND() LIMIT 1',
			'sql1'=>'select * from apilogs ORDER BY RAND() LIMIT 1'
		],
		'test1'=>[
			'sql'=>'select * from apilogs ORDER BY RAND() LIMIT 2',
			'sql1'=>'select * from apilogs ORDER BY RAND() LIMIT 1'
		],
		'test2'=>[
			'sql'=>'select * from apilogs ORDER BY RAND() LIMIT 3',
			'sql1'=>'select * from apilogs ORDER BY RAND() LIMIT 1'
		],
	];

/*
http://api.local.com/index/index/index3/
http://api.local.com/index/index/index3/data/def
http://api.local.com/index/index/index3/data/test1
http://api.local.com/index/index/index3/data/test2
*/
```