<?php

//定义项目启动时间
define('LARAVEL_START', microtime(true));

//定义文件路径
define('APP_PATH', realpath('../application').'/');
define('SYS_PATH', realpath('../system').'/');
define('BASE_PATH', realpath('../').'/');

//定义文件后缀
define('EXT', '.php');

//引入系统配置文件
require SYS_PATH . 'config' . EXT;


//类自动加载
spl_autoload_register(require SYS_PATH . 'loader' . EXT);

//获取路由方法
$route = System\Router::route(Request::method(), Request::uri());

//执行路由
$response = $route->call();

//返回数据
$response->send();
