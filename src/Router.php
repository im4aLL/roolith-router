<?php
namespace Roolith;

class Router
{
    private $requestMethod;
    private $routerArray;
    private $baseUrl;

    public function __construct()
    {
        $this->requestMethod = $_SERVER['REQUEST_METHOD'];
        $this->routerArray = [];
    }

    public function get($param, $callback)
    {
        if (!$param) {
            return;
        }

        $route = [
            'path' => ltrim($param, '/'),
            'method' => 'GET',
            'execute' => $callback,
        ];

        $this->addToRouterArray($route);

        return $this;
    }

    public function run()
    {
        switch ($this->requestMethod) {
            case 'GET':
                $this->executeGetMethod();
                break;
        }
    }

    protected function executeGetMethod()
    {
        $requestedUrl = $this->getRequestedUrl();
        $getRequestRouter = $this->getRequestedRouter($requestedUrl);

        if (is_callable($getRequestRouter['execute'])) {
            call_user_func($getRequestRouter['execute']);
        }
    }

    protected function getRequestedRouter($path)
    {
        foreach ($this->routerArray as $route) {
            if ($route['path'] == $path) {
                return $route;
                break;
            }
        }
    }

    protected function getRequestedUrl()
    {
        $currentUrl = $this->getCurrentUrl();
        $actualUrl = rtrim(str_replace($this->baseUrl, '', $currentUrl), '/');
        $actualUrl = ltrim($actualUrl, '/');

        $actualUrlArray = explode('/', $actualUrl);
        $actualUrlArray = array_map([$this, 'cleanUrlStringArray'], $actualUrlArray);
        $actualUrlArray = array_filter($actualUrlArray, 'strlen');

        return count($actualUrlArray) > 0 ? implode('/', $actualUrlArray) : '';
    }

    protected function addToRouterArray($route)
    {
        $this->routerArray[] = $route;
    }

    public function getRouteList()
    {
        return $this->routerArray;
    }

    protected function getCurrentUrl()
    {
        return "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    }

    public function setBaseUrl($url)
    {
        $this->baseUrl = $url;
    }

    private function cleanUrlString($string)
    {
        return preg_replace("/[^a-zA-Z0-9-._]+/", "", $string);
    }

    private function cleanUrlStringArray($string)
    {
        if(strstr($string, '?')) {
            $string = substr($string, 0, strpos($string, '?'));
        }

        return $this->cleanUrlString($string);
    }
}