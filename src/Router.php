<?php
namespace Roolith;

use Roolith\HttpConstants\HttpMethod;

class Router
{
    private $routerArray;
    private $response;
    private $request;
    private $requestedUrl;

    public function __construct(Response $response = null, Request $request = null)
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
            $content = isset($router['payload']) ? call_user_func_array($router['execute'], $router['payload']) : call_user_func($router['execute']);
            $this->response->body($content);
        } elseif (is_string($router['execute'])) {

            $classMethodArray = explode('@', $router['execute']);
            $className = $classMethodArray[0];
            $classMethodName = $classMethodArray[1];

            if (method_exists($className, $classMethodName)) {
                $content = isset($router['payload']) ? call_user_func_array([$className, $classMethodName], $router['payload']) : call_user_func([$className, $classMethodName]);
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
                    $patternValue = $this->matchPattern($route['path'], $this->request->getRequestedUrl());

                    if ($patternValue) {
                        $selectedRoute = $route;
                        $selectedRoute['payload'] = $patternValue;
                        break;
                    }
                }
            }
        }

        return $selectedRoute;
    }

    protected function matchPattern($routerPath, $url)
    {
        $result = null;

        $findArray = [];
        $replaceArray = [];

        $routerPathArray = explode('/', $routerPath);
        $routerPathArraySize = count($routerPathArray);
        $urlArray = explode('/', $url);

        for ($i = 0; $i < $routerPathArraySize; $i++) {
            if ($routerPathArray[$i] != $urlArray[$i]) {
                $findArray[] = $routerPathArray[$i];
                $replaceArray[] = $urlArray[$i];
            }
        }

        $replacedRouterPathArray = [];
        foreach ($routerPathArray as $item) {
            $index = array_search($item, $findArray);
            if (is_numeric($index) && $index >= 0) {
                $replacedRouterPathArray[] = $replaceArray[$index];
            } else {
                $replacedRouterPathArray[] = $item;
            }
        }

        $replacedRouterPath = implode('/', $replacedRouterPathArray);
        if ($replacedRouterPath == $url) {
            $result = $replaceArray;
        }

//        $pattern = "/{[^}]*}/";
//        preg_match_all($pattern, $routerPath, $matches);
//        $matchArray = $matches[0];
//
//        if (count($matchArray) == 0) {
//            return $result;
//        }

//        $routerPattern = preg_replace([$pattern, '/\//'], ['[a-zA-Z0-9\_\-]+', '\/'], $routerPath);
//        $actualRouterPattern = "/^$routerPattern$/s";
//        preg_match($actualRouterPattern, $url, $patternMatch);
//
//        if (count($patternMatch) > 0) {
//            $result = true;
//        }

        return $result;
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