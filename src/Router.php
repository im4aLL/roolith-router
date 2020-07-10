<?php
namespace Roolith;

use Roolith\HttpConstants\HttpMethod;

class Router
{
    private $routerArray;
    private $response;
    private $request;
    private $requestedUrl;

    public function __construct(Request $request = null, Response $response = null)
    {
        $this->routerArray = [];
        $this->response = $response ? $response : new Response();
        $this->request = $request ? $request : new Request();
    }

    public function setBaseUrl($url)
    {
        $this->request->setBaseUrl($url);
    }

    public function getBaseUrl()
    {
        return $this->request->getBaseUrl();
    }

    public function get($param, $callback)
    {
        $this->registerRoute($param, $callback, HttpMethod::GET);

        return $this;
    }

    public function post($param, $callback)
    {
        $this->registerRoute($param, $callback, HttpMethod::POST);

        return $this;
    }

    public function put($param, $callback)
    {
        $this->registerRoute($param, $callback, HttpMethod::PUT);

        return $this;
    }

    public function patch($param, $callback)
    {
        $this->registerRoute($param, $callback, HttpMethod::PATCH);

        return $this;
    }

    public function delete($param, $callback)
    {
        $this->registerRoute($param, $callback, HttpMethod::DELETE);

        return $this;
    }

    public function options($param, $callback)
    {
        $this->registerRoute($param, $callback, HttpMethod::OPTIONS);

        return $this;
    }

    public function match($array, $param, $callback)
    {
        foreach ($array as $methodName) {
            if (in_array($methodName, HttpMethod::all())) {
                $this->registerRoute($param, $callback, $methodName);
            }
        }

        return $this;
    }

    public function any($param, $callback)
    {
        foreach (HttpMethod::all() as $methodName) {
            $this->registerRoute($param, $callback, $methodName);
        }

        return $this;
    }

    public function crud($param, $callback)
    {
        $namePrefix = ltrim($param, '/');

        foreach (HttpMethod::all() as $methodName) {
            switch ($methodName) {
                case HttpMethod::GET:
                    $this->registerRoute($param, $callback, $methodName, $namePrefix.'.index');
                    $this->registerRoute($param.'/create', $callback, $methodName, $namePrefix.'.create');
                    $this->registerRoute($param.'/{param}', $callback, $methodName, $namePrefix.'.show');
                    $this->registerRoute($param.'/{param}/edit', $callback, $methodName, $namePrefix.'.edit');
                    break;
                case HttpMethod::POST:
                    $this->registerRoute($param, $callback, $methodName, $namePrefix.'.store');
                    break;
                case HttpMethod::PUT:
                case HttpMethod::PATCH:
                    $this->registerRoute($param.'/{param}', $callback, $methodName, $namePrefix.'.update');
                break;
                case HttpMethod::DELETE:
                    $this->registerRoute($param.'/{param}', $callback, $methodName, $namePrefix.'.destroy');
                    break;
            }
        }

        return $this;
    }

    public function run()
    {
        $this->requestedUrl = $this->request->getRequestedUrl();
        $methodName = $this->request->getRequestMethod();

        switch ($methodName) {
            case HttpMethod::GET:
            case HttpMethod::POST:
            case HttpMethod::PUT:
            case HttpMethod::PATCH:
            case HttpMethod::DELETE:
            case HttpMethod::OPTIONS:
                $this->executeRouteMethod($methodName);
                break;

            default:
                $this->executeRouteMethod(HttpMethod::GET);
                break;
        }
    }

    protected function executeRouteMethod($methodName)
    {
        $router = $this->getRequestedRouter($this->requestedUrl, $methodName);

        if (!$router) {
            $this->response->errorResponse("Route doesn't exists");
        }

        if (is_callable($router['execute'])) {
            $content = call_user_func($router['execute']);
            $this->response->body($content);
        } elseif (is_string($router['execute'])) {

            $classMethodArray = explode('@', $router['execute']);
            $className = $classMethodArray[0];
            $classMethodName = $classMethodArray[1];

            if (method_exists($className, $classMethodName)) {
                $content = call_user_func([$className, $classMethodName]);
                $this->response->body($content);
            } else {
                $this->response->errorResponse("$classMethodName method doesn't exist in $className");
            }
        }
    }

    protected function getRequestedRouter($path, $method)
    {
        $selectedRoute = null;

        foreach ($this->routerArray as $route) {
            if ($route['method'] == $method) {
                if ($route['path'] == $path) {
                    $selectedRoute = $route;
                    break;
                } elseif (strstr($route['path'], '{')) {
                    $pattern = "/{[^}]*}/";
                    preg_match_all($pattern, $route['path'], $matches);
                    print_r($matches[0]);
                }
            }
        }

        return $selectedRoute;
    }

    protected function addToRouterArray($route)
    {
        $this->routerArray[] = $route;

        return $this;
    }

    public function getRouteList()
    {
        return $this->routerArray;
    }

    private function registerRoute($param, $callback, $method, $name = '')
    {
        if (!$param || !$callback) {
            return $this;
        }

        $routeArray = [];

        if (is_array($param)) {
            foreach ($param as $urlParam) {
                $this->addRouteToRouteArray($routeArray, $urlParam, $method, $callback, $name);
            }
        } else {
            $this->addRouteToRouteArray($routeArray, $param, $method, $callback, $name);
        }

        foreach ($routeArray as $route) {
            $this->addToRouterArray($route);
        }

        return $this;
    }

    private function addRouteToRouteArray(&$routeArray, $param, $method, $callback, $name = '') {
        $routeArray[] = [
            'path' => '/'.ltrim($param, '/'),
            'method' => $method,
            'execute' => $callback,
            'name' => $name,
        ];
    }

    public function name($string)
    {
        if (count($this->routerArray) == 0) {
            return false;
        }

        $this->routerArray[count($this->routerArray) - 1]['name'] = $string;

        return $this;
    }

    public function getUrlByName($string)
    {
        $url = '';

        foreach ($this->routerArray as $route) {
            if ($route['name'] == $string) {
                $url = $route['path'];
                break;
            }
        }

        return $this->getBaseUrl().ltrim($url, '/');
    }
}