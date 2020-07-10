<?php
namespace Roolith;

use Roolith\HttpConstants\HttpMethod;

class Router
{
    private $routerArray;
    private $response;
    private $request;
    private $requestedUrl;

    public function __construct()
    {
        $this->routerArray = [];
        $this->response = new Response();
        $this->request = new Request();
    }

    public function setBaseUrl($url)
    {
        $this->request->setBaseUrl($url);
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
                $this->executeRouteMethod($methodName);
                break;
        }
    }

    protected function executeRouteMethod($methodName)
    {
        $router = $this->getRequestedRouter($this->requestedUrl, $methodName);

        if (is_callable($router['execute'])) {
            $content = call_user_func($router['execute']);
            $this->response->body($content);
        }
    }

    protected function getRequestedRouter($path, $method)
    {
        $selectedRoute = null;

        foreach ($this->routerArray as $route) {
            if ($route['path'] == $path && $route['method'] == $method) {
                $selectedRoute = $route;
                break;
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

    private function registerRoute($param, $callback, $method)
    {
        if (!$param || !$callback) {
            return $this;
        }

        $routeArray = [];

        if (is_array($param)) {
            foreach ($param as $urlParam) {
                $routeArray[] = [
                    'path' => ltrim($urlParam, '/'),
                    'method' => $method,
                    'execute' => $callback,
                ];
            }
        } else {
            $routeArray[] = [
                'path' => ltrim($param, '/'),
                'method' => $method,
                'execute' => $callback,
            ];
        }

        foreach ($routeArray as $route) {
            $this->addToRouterArray($route);
        }

        return $this;
    }
}