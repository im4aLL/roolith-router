<?php
namespace Roolith\Route;

use Roolith\Route\HttpConstants\HttpMethod;
use Roolith\Route\HttpConstants\HttpResponseCode;
use Roolith\Route\Interfaces\RouterInterface;

class Router extends RouterBase implements RouterInterface
{
    /**
     * Router constructor.
     *
     * @param array $settings
     * @param Response|null $response
     * @param Request|null $request
     */
    public function __construct($settings = [], Response $response = null, Request $request = null)
    {
        $response = $response ? $response : new Response();
        $request = $request ? $request : new Request();

        parent::__construct($response, $request);

        $this->applySettings($settings);
    }

    /**
     * Define GET route
     *
     * @param $param
     * @param $callback
     * @return $this
     */
    public function get($param, $callback): static
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
    public function post($param, $callback): static
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
    public function put($param, $callback): static
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
    public function patch($param, $callback): static
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
    public function delete($param, $callback): static
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
    public function options($param, $callback): static
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
    public function match($array, $param, $callback): static
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
    public function any($param, $callback): static
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
    public function crud($param, $callback): static
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
    private function crudCallback($callback, $methodName): string
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
    public function redirect($fromUrl, $toUrl, int $statusCode = HttpResponseCode::MOVED_PERMANENTLY): static
    {
        $this->registerRedirectRoute($fromUrl, $toUrl, $statusCode);

        return $this;
    }

    /**
     * Define group for routes
     *
     * @param $settings
     * @param $callback
     * @return Router
     */
    public function group($settings, $callback): static
    {
        $this->setGroupSettings($settings);
        call_user_func($callback);
        $this->resetGroupSettings();

        return $this;
    }

    /**
     * Match the requested URL with a route list and execute it's callable method
     *
     * @return $this
     */
    public function run(): static
    {
        $methodName = $this->request->getRequestMethod();
        $router = $this->getRequestedRouter($this->request->getRequestedUrl(), $methodName);

        if (isset($router['middleware'])) {
            $isProcessNext = call_user_func([new $router['middleware'](), 'process'], $this->request, $this->response);
            if (!$isProcessNext) {
                $this->response->errorResponse($this->getViewHtmlByStatusCode(HttpResponseCode::BAD_REQUEST, "Invalid request"));
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
     * Get active route
     *
     * @return array
     */
    public function activeRoute(): array
    {
        $methodName = $this->request->getRequestMethod();

        return $this->getRequestedRouter($this->request->getRequestedUrl(), $methodName);
    }

    /**
     * Register a route
     * If param is array then register multiple route
     *
     * @param $param
     * @param $callback
     * @param $method
     * @param string $name
     * @return void
     */
    private function registerRoute($param, $callback, $method, string $name = ''): void
    {
        if (!$param || !$callback) {
            return;
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

    }

    /**
     * Register redirect route
     *
     * @param $fromUrl
     * @param $toUrl
     * @param $statusCode
     * @return void
     */
    private function registerRedirectRoute($fromUrl, $toUrl, $statusCode): void
    {
        if (str_starts_with($toUrl, 'http')) {
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

    }

    /**
     * Add group settings to route
     *
     * @param $route
     * @param $groupSettings
     * @return void
     */
    private function addGroupSettingsToRoute(&$route, $groupSettings): void
    {
        if (isset($groupSettings['middleware'])) {
            $route['middleware'] = $groupSettings['middleware'];
        }

        if (isset($groupSettings['urlPrefix'])) {
            $path = '/'.ltrim($groupSettings['urlPrefix'], '/').$route['path'];
            $route['path'] = rtrim($path, '/');
        }

        if (isset($groupSettings['namePrefix'])) {
            $route['name'] = $groupSettings['namePrefix'].$route['name'];
        }
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
     * @return void
     */
    private function addRouteToRouteArray(&$routeArray, $param, $method, $callback, $name = ''): void
    {
        if (str_contains($param, '?')) {
            $paramArray = explode('/', $param);
            $size = count($paramArray);

            $modifiedParamArray = [];
            for ($i = 0; $i < $size; $i++) {
                if (str_contains($paramArray[$i], '?')) {
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

    }

    /**
     * Adding name to last route item
     *
     * @param $string
     * @return $this|bool
     */
    public function name($string): bool|static
    {
        if (count($this->routerArray) == 0) {
            return false;
        }

        $namePrefix = end($this->routerArray)['name'] ?? '';
        $this->routerArray[count($this->routerArray) - 1]['name'] = $namePrefix.$string;

        return $this;
    }

    /**
     * Adding middleware to last route item
     *
     * @param $middlewareClass
     * @return $this|bool
     */
    public function middleware($middlewareClass): bool|static
    {
        if (count($this->routerArray) == 0) {
            return false;
        }

        $this->routerArray[count($this->routerArray) - 1]['middleware'] = $middlewareClass;

        return $this;
    }

    /**
     * Apply settings
     *
     * @param $settings
     * @return void
     */
    private function applySettings($settings): void
    {
        if (isset($settings['base_url'])) {
            $this->setBaseUrl($settings['base_url']);
        }

        if (isset($settings['view_dir'])) {
            $this->setViewDir($settings['view_dir']);
        }

        if (isset($settings['use_di'])) {
            $this->setUseDi($settings['use_di']);
        }

    }
}
