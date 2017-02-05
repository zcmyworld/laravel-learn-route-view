## 需求设计

1.  框架使用者在application目录下实现业务逻辑
2.  根据http请求中不同的url和method，处理的方法也不同（路由）


## 实现一，返回简单字符串

在 application 目录下创建文件routes.php

	<?php

	return array(
		//当请求为 / 且方法为 GET 的时候，会执行该函数进行处理
	    'GET /' => function()
	    {
			return "helloworld";
	    }
	);

即当url为 / 且方法为 GET 时, 返回字符串 helloworld

## 系统调用

在 /public/index.php 文件最后加上

	//获取路由方法
	$route = System\Router::route(Request::method(), Request::uri());

	//执行路由
	$response = $route->call();

	//返回数据
	$response->send();

先根据http请求中的url和uri获取对应的处理函数，然后执行该方法获取返回值，最后返回到客户端。


## url 处理

增加一个处理与保存http请求信息的类 Request:

在 system 目录下创建文件request.php

Request方法主要用于处理$_GET, $_POST, $_SERVER中的变量并进行封装，先提供两个功能： 获取当前请求 url 获取当前请求的方法：

	<?php

    namespace System;

    class Request
    {

        public static $uri;

        public static function uri()
        {
            if ( ! is_null(static::$uri))
            {
                return static::$uri;
            }

            if ( ! isset($_SERVER['REQUEST_URI']))
            {
                throw new \Exception('Unable to determine the request URI.');
            }

            $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

            return static::$uri = static::tidy($uri);
        }

        // 格式化url
        private static function tidy($uri)
        {
            return ($uri != '/') ? strtolower(trim($uri, '/')) : '/';
        }

        public static function method()
        {
            return (isset($_POST['request_method'])) ? $_POST['request_method'] : $_SERVER['REQUEST_METHOD'];
        }
    }

系统可以通过 Request::method()来获取当前请求的方法，可以通过Request::uri()来获取当前请求的url

我们将根据http请求中的uri和method来判断使用哪一个路由进行处理，于是创建一个路由工厂类Router:

在 system 目录下添加 router.php:

	<?php

	namespace System;

	class Router
	{
	    public static $routes;

	    public static function route($method, $uri)
	    {
	        $uri = ($uri != '/') ? '/' . $uri : $uri;

	        static::$routes = require APP_PATH.'routes'.EXT;

	        if (isset(static::$routes[$method.' '.$uri]))
	        {
	            return new Route(static::$routes[$method.' '.$uri]);
	        }
	    }

	}

对于路由对应的处理函数，我们不直接进行调用，而是使用 Route 类来进行动态代理，好处是便于添加请求拦截器，日志等。

在 system 目录下添加 route.php 文件：

	<?php

	namespace System;

	class Route
	{
	    public $route;

	    public $parameters;

	    public function __construct($route, $parameters = array())
	    {
	        $this->route = $route;
	        $this->parameters = $parameters;
	    }

	    public function call()
	    {
	        if (is_callable($this->route))
	        {
	            $response = call_user_func_array($this->route, $this->parameters);
	        }

	        $response = new Response($response);

	        return $response;
	    }
	}

在 system 目录下增加 response.php 文件：

	<?php

	namespace System;

	class Response
	{
	    public $content;

	    public $status;

	    public $headers = array();

	    private $statuses = array(
	        100 => 'Continue',
	        101 => 'Switching Protocols',
	        200 => 'OK',
	        201 => 'Created',
	        202 => 'Accepted',
	        203 => 'Non-Authoritative Information',
	        204 => 'No Content',
	        205 => 'Reset Content',
	        206 => 'Partial Content',
	        207 => 'Multi-Status',
	        300 => 'Multiple Choices',
	        301 => 'Moved Permanently',
	        302 => 'Found',
	        303 => 'See Other',
	        304 => 'Not Modified',
	        305 => 'Use Proxy',
	        307 => 'Temporary Redirect',
	        400 => 'Bad Request',
	        401 => 'Unauthorized',
	        402 => 'Payment Required',
	        403 => 'Forbidden',
	        404 => 'Not Found',
	        405 => 'Method Not Allowed',
	        406 => 'Not Acceptable',
	        407 => 'Proxy Authentication Required',
	        408 => 'Request Timeout',
	        409 => 'Conflict',
	        410 => 'Gone',
	        411 => 'Length Required',
	        412 => 'Precondition Failed',
	        413 => 'Request Entity Too Large',
	        414 => 'Request-URI Too Long',
	        415 => 'Unsupported Media Type',
	        416 => 'Requested Range Not Satisfiable',
	        417 => 'Expectation Failed',
	        422 => 'Unprocessable Entity',
	        423 => 'Locked',
	        424 => 'Failed Dependency',
	        500 => 'Internal Server Error',
	        501 => 'Not Implemented',
	        502 => 'Bad Gateway',
	        503 => 'Service Unavailable',
	        504 => 'Gateway Timeout',
	        505 => 'HTTP Version Not Supported',
	        507 => 'Insufficient Storage',
	        509 => 'Bandwidth Limit Exceeded'
	    );


	    public function __construct($content, $status = 200)
	    {
	        $this->content = $content;
	        $this->status = $status;
	    }


	    public function send()
	    {
	        //默认返回Content-Type
	        if ( ! array_key_exists('Content-Type', $this->headers))
	        {
	            $this->header('Content-Type', 'text/html; charset=utf-8');
	        }

	        // 返回header
	        if ( ! headers_sent())
	        {
	            //获取http协议
	            $protocol = (isset($_SERVER['SERVER_PROTOCOL'])) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';

	            header($protocol.' '.$this->status.' '.$this->statuses[$this->status]);

	            foreach ($this->headers as $name => $value)
	            {
	                header($name.': '.$value, true);
	            }
	        }

	        //输出内容
	        echo (string) $this->content;
	    }

	    public function header($name, $value)
	    {
	        $this->headers[$name] = $value;
	        return $this;
	    }
	}

Response 类用于决定http请求中返回的内容和header

整个http请求处理过程如下：

1. 通过 Request 类获取 url 和 method
2. 通过 Router 工厂来返回对应的处理函数，使用工厂方法的原因是，可以在工厂中扩展路由精确匹配或者路由模糊匹配等功能
3. 通过动态代理来执行路由处理函数，可以在路由代理类中加入日志，请求拦截器等功能，而这些功能可以对框架使用者透明
4. 执行路由处理函数获取内容后，通过response方法进行输出

## 实现二，返回html视图

在 application 目录下创建 views 目录，该目录用于存放视图文件。再创建home目录，用来存放首页视图文件，再创建index.php，即视图文件，目录结构如下：

* application
	* views
		* home
			* index.php

在index.php中添加html代码：

	<!DOCTYPE html>
	<html>
	<head>
	    <meta charset="utf-8">
	    <title>Let's learn Laravel!</title>
	</head>
	<body>
	<h1>Let's learn Laravel!</h1>
	</body>
	</html>

将 application/routes.php 改为

	<?php

	return array(
	    'GET /' => function()
	    {
	        return View::make('home/index');
	    }
	);

即当请求 / 方法为 GET 的时候，访问 home 目录下的这个index.php 视图文件，其中，View类完成视图文件读取的功能

在 system 目录下创建 view.php 文件：

	<?php

	namespace System;

	class View
	{
	    public $view;

	    public $content = "";

	    public function __construct($view)
	    {
	        $this->view = $view;
	        $this->content = $this->load($view);
	    }

	    public static function make($view)
	    {
	        return new self($view);
	    }

	    private function load($view)
	    {
	        if (file_exists($path = APP_PATH.'views/'.$view.EXT))
	        {
	            return file_get_contents($path);
	        }

	        elseif (file_exists($path = SYS_PATH.'views/'.$view.EXT))
	        {
	            return file_get_contents($path);
	        }

	        else
	        {
	            throw new \Exception("View [$view] doesn't exist.");
	        }
	    }

	    public function __toString()
	    {
	        return (string) $this->content;
	    }

	}


通过 make 方法来返回类的实例，类初始化的时候，根据文件路径使用 file_get_contents方法读取文件。
View类中的content属性以字符串的形式保存整个视图文件。

由于 application/routers中的函数返回的时 View 对象，而 Response类中的 send() 方法通过 echo (stirng) $this-content;的方式输出内容。所以， View 类中需要重写 __toString() 方法来保证Response类进行类型转换获得的是字符串。

## 实现三，数据绑定

## 需求设计

在控制器里面通过 bind() 方法来进行数据绑定。

将 application/routes.php 修改成：

	<?php

	return array(
	    'GET /' => function()
	    {
	        return View::make('home/index')->bind("key", "Let's learn laravel!");
	    }
	);

在视图文件中直接输出，将application/views/home/index.php改成：

	<!DOCTYPE html>
	<html>
	<head>
	    <meta charset="utf-8">
	    <title>Let's learn Laravel!</title>
	</head>
	<body>
	<h1><?php echo $key?></h1>
	</body>
	</html>

然后，我们将修改 View 类来完成这个实现

修改 View.php 增加公有属性

	public $data = array();

增加 bind() 方法：

	public function bind($key, $value)
    {
        $this->data[$key] = $value;
        return $this;
    }

新增get方法

	public function get()
    {
        extract($this->data);

        ob_start();

        echo eval('?>' . $this->content);

        return ob_get_clean();
    }

重写 __toString()

	public function __toString()
    {
        return $this->get();
    }

在 get 方法中完成了视图文件的数据绑定