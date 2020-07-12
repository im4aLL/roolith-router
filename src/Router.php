<?php
namespace Roolith;

use Roolith\HttpConstants\HttpMethod;
use Roolith\HttpConstants\HttpResponseCode;

class Router
{
    /**
     * List of all routes
     *
     * @var array
     */
    private $routerArray;

    /**
     * Response class instance
     *
     * @var Response
     */
    private $response;

    /**
     * Request class instance
     *
     * @var Request
     */
    private $request;

    /**
     * Group route settings value
     *
     * @var array
     */
    private $groupSettings;

    /**
     * Router constructor.
     *
     * @param Response|null $response
     * @param Request|null $request
     */
    public function __construct(Response $response = null, Request $request = null)
    {
        $this->routerArray = [];
        $this->response = $response ? $response : new Response();
        $this->request = $request ? $request : new Request();
        $this->groupSettings = [];
    }

    /**
     * Set base url to request
     *
     * @param $url
     * @return $this
     */
    public function setBaseUrl($url)
    {
        $this->request->setBaseUrl($url);

        return $this;
    }

    /**
     * Get base url from request
     *
     * @return mixed
     */
    public function getBaseUrl()
    {
        return $this->request->getBaseUrl();
    }

    /**
     * Get current group router settings
     *
     * @return array|bool
     */
    public function getGroupSettings()
    {
        return count($this->groupSettings) > 0 ? $this->groupSettings : false;
    }

    /**
     * Set current group router settings
     *
     * @param $groupSettings
     * @return $this
     */
    public function setGroupSettings($groupSettings)
    {
        $this->groupSettings = $groupSettings;

        return $this;
    }

    /**
     * Reset current group settings
     *
     * @return $this
     */
    public function resetGroupSettings()
    {
        $this->groupSettings = [];

        return $this;
    }

    /**
     * Define GET route
     *
     * @param $param
     * @param $callback
     * @return $this
     */
    public function get($param, $callback)
    {
        $this->registerRoute($param, $callback, HttpMethod::GET);

        return $this;
    }

    /**
     * Define POST route
     *
     * @param $param
     * @param $callback
     * @return $this
     */
    public function post($param, $callback)
    {
        $this->registerRoute($param, $callback, HttpMethod::POST);

        return $this;
    }

    /**
     * Define PUT route
     *
     * @param $param
     * @param $callback
     * @return $this
     */
    public function put($param, $callback)
    {
        $this->registerRoute($param, $callback, HttpMethod::PUT);

        return $this;
    }

    /**
     * Define PATCH route
     *
     * @param $param
     * @param $callback
     * @return $this
     */
    public function patch($param, $callback)
    {
        $this->registerRoute($param, $callback, HttpMethod::PATCH);

        return $this;
    }

    /**
     * Define DELETE route
     *
     * @param $param
     * @param $callback
     * @return $this
     */
    public function delete($param, $callback)
    {
        $this->registerRoute($param, $callback, HttpMethod::DELETE);

        return $this;
    }

    /**
     * Define OPTIONS route
     *
     * @param $param
     * @param $callback
     * @return $this
     */
    public function options($param, $callback)
    {
        $this->registerRoute($param, $callback, HttpMethod::OPTIONS);

        return $this;
    }

    /**
     * Define multiple route method as array
     *
     * @param $array
     * @param $param
     * @param $callback
     * @return $this
     */
    public function match($array, $param, $callback)
    {
        foreach ($array as $methodName) {
            if (in_array($methodName, HttpMethod::all())) {
                $this->registerRoute($param, $callback, $methodName);
            }
        }

        return $this;
    }

    /**
     * Defined wildcard route
     *
     * @param $param
     * @param $callback
     * @return $this
     */
    public function any($param, $callback)
    {
        foreach (HttpMethod::all() as $methodName) {
            $this->registerRoute($param, $callback, $methodName);
        }

        return $this;
    }

    /**
     * Define crud route
     *
     * @param $param
     * @param $callback
     * @return $this
     */
    public function crud($param, $callback)
    {
        $namePrefix = ltrim($param, '/');

        foreach (HttpMethod::all() as $methodName) {
            switch ($methodName) {
                case HttpMethod::GET:
                    $this->registerRoute($param, $this->crudCallback($callback, 'index'), $methodName, $namePrefix.'.index');
                    $this->registerRoute($param.'/create', $this->crudCallback($callback, 'create'), $methodName, $namePrefix.'.create');
                    $this->registerRoute($param.'/{param}', $this->crudCallback($callback, 'show'), $methodName, $namePrefix.'.show');
                    $this->registerRoute($param.'/{param}/edit', $this->crudCallback($callback, 'edit'), $methodName, $namePrefix.'.edit');
                    break;
                case HttpMethod::POST:
                    $this->registerRoute($param, $this->crudCallback($callback, 'store'), $methodName, $namePrefix.'.store');
                    break;
                case HttpMethod::PUT:
                case HttpMethod::PATCH:
                    $this->registerRoute($param.'/{param}', $this->crudCallback($callback, 'update'), $methodName, $namePrefix.'.update');
                break;
                case HttpMethod::DELETE:
                    $this->registerRoute($param.'/{param}', $this->crudCallback($callback, 'destroy'), $methodName, $namePrefix.'.destroy');
                    break;
            }
        }

        return $this;
    }

    /**
     * Crud callback add method name
     *
     * @param $callback
     * @param $methodName
     * @return string
     */
    private function crudCallback($callback, $methodName)
    {
        if (is_string($callback)) {
            return $callback.'@'.$methodName;
        }

        return $callback;
    }

    /**
     * Define redirect route
     *
     * @param $fromUrl
     * @param $toUrl
     * @param int $statusCode
     * @return $this
     */
    public function redirect($fromUrl, $toUrl, $statusCode = HttpResponseCode::MOVED_PERMANENTLY)
    {
        $this->registerRedirectRoute($fromUrl, $toUrl, $statusCode);

        return $this;
    }

    /**
     * Define group for routes
     *
     * @param $settings
     * @param $callback
     */
    public function group($settings, $callback)
    {
        $this->setGroupSettings($settings);
        call_user_func($callback);
    }

    /**
     * Match requested URL with route list and execute it's callable method
     *
     * @return $this
     */
    public function run()
    {
        $methodName = $this->request->getRequestMethod();
        $router = $this->getRequestedRouter($this->request->getRequestedUrl(), $methodName);

        if (isset($router['middleware'])) {
            $isProcessNext = call_user_func([$router['middleware'], 'process'], $this->request, $this->response);
            if (!$isProcessNext) {
                $this->response->errorResponse("Invalid request");
                return $this;
            }
        }

        switch ($methodName) {
            case HttpMethod::GET:
            case HttpMethod::POST:
            case HttpMethod::PUT:
            case HttpMethod::PATCH:
            case HttpMethod::DELETE:
            case HttpMethod::OPTIONS:
                $this->executeRouteMethod($router);
                break;
        }

        return $this;
    }

    /**
     * Execute router callback method
     *
     * @param $router
     * @return $this
     */
    protected function executeRouteMethod($router)
    {
        if (!$router) {
            $this->response->errorResponse("Route doesn't exists");
            return $this;
        }

        if (isset($router['redirect'])) {
            $this->response->setStatusCode($router['code']);
            $this->response->redirect($router['redirect']);
            return $this;
        }

        if (isset($router['execute']) && is_callable($router['execute'])) {
            $content = isset($router['payload']) ? call_user_func_array($router['execute'], $router['payload']) : call_user_func($router['execute']);
            $this->response->body($content);
        } elseif (isset($router['execute']) && is_string($router['execute'])) {
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

        return $this;
    }

    /**
     * Match requested url with router pattern
     *
     * @param $path
     * @param $method
     * @return mixed|null
     */
    protected function getRequestedRouter($path, $method)
    {
        $selectedRoute = null;

        foreach ($this->routerArray as $route) {
            if ($route['method'] == $method) {
                if ($route['path'] == $path) {
                    $selectedRoute = $route;
                    break;
                } elseif (strstr($route['path'], '{')) {
                    $patternValue = $this->matchPlain($route['path'], $path);

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

    /**
     * Route match method in plain
     *
     * @param $routerPath
     * @param $url
     * @return array|bool
     */
    protected function matchPlain($routerPath, $url)
    {
        $result = false;

        $findArray = [];
        $replaceArray = [];

        $routerPathArray = explode('/', $routerPath);
        $routerPathArraySize = count($routerPathArray);
        $urlArray = explode('/', $url);

        for ($i = 0; $i < $routerPathArraySize; $i++) {
            if (isset($routerPathArray[$i]) && isset($urlArray[$i]) && $routerPathArray[$i] != $urlArray[$i]) {
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

            $this->request->setRequestedParam($findArray, $replaceArray);
        }

        return $result;
    }

    /**
     * Route match method with regex
     *
     * @param $routerPath
     * @param $url
     * @return array|bool
     */
    protected function matchPattern($routerPath, $url)
    {
        $result = false;

        $pattern = "/{[^}]*}/";
        preg_match_all($pattern, $routerPath, $matches);
        $matchArray = $matches[0];

        if (count($matchArray) == 0) {
            return $result;
        }

        $routerPattern = preg_replace([$pattern, '/\//'], ['[a-zA-Z0-9\_\-]+', '\/'], $routerPath);
        $actualRouterPattern = "/^$routerPattern$/s";
        preg_match($actualRouterPattern, $url, $patternMatch);

        if (count($patternMatch) == 0) {
            return $result;
        }

        $valueArray = [];

        $routerPathArray = explode('/', $routerPath);
        $routerPathArraySize = count($routerPathArray);
        $urlArray = explode('/', $url);

        for ($i = 0; $i < $routerPathArraySize; $i++) {
            if ($routerPathArray[$i] != $urlArray[$i]) {
                $valueArray[] = $urlArray[$i];
            }
        }

        $result = $valueArray;
        $this->request->setRequestedParam($matchArray, $valueArray);

        return $result;
    }

    /**
     * Adding a route to router array
     *
     * @param $route
     * @return $this
     */
    protected function addToRouterArray($route)
    {
        $this->routerArray[] = $route;

        return $this;
    }

    /**
     * Get list of registered router
     *
     * @return array
     */
    public function getRouteList()
    {
        return $this->routerArray;
    }

    /**
     * Register a route
     * If param is array then register multiple route
     *
     * @param $param
     * @param $callback
     * @param $method
     * @param string $name
     * @return $this
     */
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

        $groupSettings = $this->getGroupSettings();

        foreach ($routeArray as $route) {
            if ($groupSettings) {
                $this->addGroupSettingsToRoute($route, $groupSettings);
            }

            $this->addToRouterArray($route);
        }

        return $this;
    }

    /**
     * Add group settings to route
     *
     * @param $route
     * @param $groupSettings
     * @return Router
     */
    private function addGroupSettingsToRoute(&$route, $groupSettings)
    {
        if (isset($groupSettings['middleware'])) {
            $route['middleware'] = $groupSettings['middleware'];
        }

        if (isset($groupSettings['urlPrefix'])) {
            $route['path'] = '/'.ltrim($groupSettings['urlPrefix'], '/').$route['path'];
        }

        if (isset($groupSettings['namePrefix'])) {
            $route['name'] = $groupSettings['namePrefix'];
        }

        return $this;
    }

    /**
     * Adding route to router array
     * Note: Reference passed
     *
     * @param $routeArray
     * @param $param
     * @param $method
     * @param $callback
     * @param string $name
     * @return $this
     */
    private function addRouteToRouteArray(&$routeArray, $param, $method, $callback, $name = '') {
        if (strstr($param, '?')) {
            $paramArray = explode('/', $param);
            $size = count($paramArray);

            $modifiedParamArray = [];
            for ($i = 0; $i < $size; $i++) {
                if (strstr($paramArray[$i], '?')) {
                    $this->addRouteToRouteArray($routeArray, implode('/', $modifiedParamArray), $method, $callback, $name);
                    $this->addRouteToRouteArray($routeArray, str_replace('?', '', $param), $method, $callback, $name);
                } else {
                    $modifiedParamArray[] = $paramArray[$i];
                }
            }
        } else {
            $routeArray[] = [
                'path' => '/'.ltrim($param, '/'),
                'method' => $method,
                'execute' => $callback,
                'name' => $name,
            ];
        }

        return $this;
    }

    /**
     * Register redirect route
     *
     * @param $fromUrl
     * @param $toUrl
     * @param $statusCode
     * @return $this
     */
    private function registerRedirectRoute($fromUrl, $toUrl, $statusCode)
    {
        if (strpos($toUrl, 'http') === 0) {
            $redirectUrl = $toUrl;
        } else {
            $redirectUrl = $this->getBaseUrl().ltrim($toUrl, '/');
        }

        $route = [
            'path' => '/'.ltrim($fromUrl, '/'),
            'redirect' => $redirectUrl,
            'method' => HttpMethod::GET,
            'code' => $statusCode,
        ];

        $this->addToRouterArray($route);

        return $this;
    }

    /**
     * Adding name to last route item
     *
     * @param $string
     * @return $this|bool
     */
    public function name($string)
    {
        if (count($this->routerArray) == 0) {
            return false;
        }

        $namePrefix = $this->routerArray[count($this->routerArray) - 1]['name'] ? $this->routerArray[count($this->routerArray) - 1]['name'] : '';
        $this->routerArray[count($this->routerArray) - 1]['name'] = $namePrefix.$string;

        return $this;
    }

    /**
     * Get full URL by router name
     *
     * @param $string
     * @return string
     */
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

    /**
     * Adding middleware to last route item
     *
     * @param $middlewareClass
     * @return $this|bool
     */
    public function middleware($middlewareClass)
    {
        if (count($this->routerArray) == 0) {
            return false;
        }

        $this->routerArray[count($this->routerArray) - 1]['middleware'] = $middlewareClass;

        return $this;
    }
}
