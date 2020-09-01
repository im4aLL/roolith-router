<?php
namespace Roolith\Route;

use Roolith\Route\HttpConstants\HttpResponseCode;

abstract class RouterBase
{
    /**
     * List of all routes
     *
     * @var array
     */
    protected $routerArray;

    /**
     * Response class instance
     *
     * @var Response
     */
    protected $response;

    /**
     * Request class instance
     *
     * @var Request
     */
    protected $request;

    /**
     * Group route settings value
     *
     * @var array
     */
    protected $groupSettings;

    /**
     * @var string
     */
    protected $viewDir;


    public function __construct(Response $response, Request $request)
    {
        $this->routerArray = [];
        $this->response = $response;
        $this->request = $request;
        $this->groupSettings = [];
        $this->viewDir = null;
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
     * Set view dir
     *
     * @param $dir
     * @return $this
     */
    public function setViewDir($dir)
    {
        $this->viewDir = $dir;

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
        return $this->groupSettings && count($this->groupSettings) > 0 ? $this->groupSettings : false;
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
     * Execute router callback method
     *
     * @param $router
     * @return $this
     */
    protected function executeRouteMethod($router)
    {
        if (!$router) {
            $this->response->errorResponse($this->getViewHtmlByStatusCode(HttpResponseCode::NOT_FOUND, "Route doesn't exists"));
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
                $content = isset($router['payload']) ? call_user_func_array([new $className, $classMethodName], $router['payload']) : call_user_func([new $className, $classMethodName]);
                $this->response->body($content);
            } else {
                $this->response->errorResponse($this->getViewHtmlByStatusCode(HttpResponseCode::NOT_FOUND, "$classMethodName method doesn't exist in $className"));
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
     * Get view html by status code
     *
     * @param $statusCode
     * @param string $message
     * @return string
     */
    public function getViewHtmlByStatusCode($statusCode, $message = '')
    {
        if (!$this->viewDir) {
            return $message;
        }

        $filePath = $this->viewDir . '/' . $statusCode . '.php';
        if (file_exists($filePath)) {
            ob_start();
            include $filePath;
            $output = ob_get_contents();
            ob_end_clean();
            return $output;
        }

        return $message;
    }
}
