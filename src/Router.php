<?php
namespace Roolith\Route;

use Roolith\Route\HttpConstants\HttpMethod;
use Roolith\Route\HttpConstants\HttpResponseCode;
use Roolith\Route\Interfaces\RouterInterface;

class Router extends RouterBase implements RouterInterface
{
    /**
     * Start index of the most recent registration slice in routerArray.
     * name() and middleware() apply to the whole slice.
     *
     * @var int
     */
    private int $registrationSliceStart = 0;

    /**
     * Stack of outer group settings for nested group() calls.
     *
     * @var array
     */
    private array $groupSettingsStack = [];
    /**
     * Router constructor.
     *
     * @param array $settings
     * @param Response|null $response
     * @param Request|null $request
     */
    public function __construct($settings = [], ?Response $response = null, ?Request $request = null)
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
        $this->beginRegistrationSlice();
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
        $this->beginRegistrationSlice();
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
        $this->beginRegistrationSlice();
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
        $this->beginRegistrationSlice();
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
        $this->beginRegistrationSlice();
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
        $this->beginRegistrationSlice();
        $this->registerRoute($param, $callback, HttpMethod::OPTIONS);

        return $this;
    }

    /**
     * Define multiple route method as array
     *
     * @param $array
     * @param $param
     * @param $callback
     * @param string $name
     * @return $this
     */
    public function match($array, $param, $callback, string $name = ''): static
    {
        $this->beginRegistrationSlice();

        foreach ($array as $methodName) {
            $normalizedMethodName = strtoupper($methodName);

            if (in_array($normalizedMethodName, HttpMethod::all())) {
                $this->registerRoute($param, $callback, $normalizedMethodName, $name);
            }
        }

        return $this;
    }

    /**
     * Defined wildcard route
     *
     * @param $param
     * @param $callback
     * @param string $name
     * @return $this
     */
    public function any($param, $callback, string $name = ''): static
    {
        $this->beginRegistrationSlice();

        foreach (HttpMethod::all() as $methodName) {
            $this->registerRoute($param, $callback, $methodName, $name);
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
        $this->beginRegistrationSlice();

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
                    $this->registerRoute($param.'/{param}', $this->crudCallback($callback, 'update'), $methodName, $namePrefix.'._update');
                    $this->registerRoute($param.'/{param}/delete', $this->crudCallback($callback, 'destroy'), $methodName, $namePrefix.'._destroy');
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
     * @return mixed
     */
    private function crudCallback($callback, $methodName): mixed
    {
        if (is_string($callback)) {
            return $callback.'@'.$methodName;
        }

        return $callback;
    }

    /**
     * Define redirect route
     *
     * Redirect sources must be literal paths: optional placeholders
     * (e.g. '/a/{x?}') are not expanded here, so register each
     * concrete source path with a separate redirect() call.
     *
     * @param $fromUrl
     * @param $toUrl
     * @param int $statusCode
     * @return $this
     */
    public function redirect($fromUrl, $toUrl, int $statusCode = HttpResponseCode::MOVED_PERMANENTLY): static
    {
        $this->beginRegistrationSlice();
        $this->registerRedirectRoute($fromUrl, $toUrl, $statusCode);

        return $this;
    }

    /**
     * Define group for routes
     *
     * Nested groups merge into the outer group: urlPrefix and namePrefix
     * concatenate, middleware appends outer first. The callback receives
     * this router instance, so both `function ($router) { ... }` and
     * `function () use ($router) { ... }` styles keep working.
     *
     * @param $settings
     * @param $callback
     * @return Router
     */
    public function group($settings, $callback): static
    {
        $this->groupSettingsStack[] = $this->groupSettings;
        $this->groupSettings = $this->mergeGroupSettings($this->groupSettings, $settings);

        try {
            call_user_func($callback, $this);
        } finally {
            $this->groupSettings = array_pop($this->groupSettingsStack) ?? [];
        }

        return $this;
    }

    /**
     * Merge inner group settings into outer group settings.
     *
     * @param $outer
     * @param $inner
     * @return array
     */
    private function mergeGroupSettings($outer, $inner): array
    {
        $merged = is_array($outer) ? $outer : [];

        if (isset($inner['urlPrefix'])) {
            $outerPrefix = isset($merged['urlPrefix']) ? trim((string) $merged['urlPrefix'], '/') : '';
            $innerPrefix = trim((string) $inner['urlPrefix'], '/');
            $merged['urlPrefix'] = $outerPrefix === '' ? $innerPrefix : $outerPrefix.'/'.$innerPrefix;
        }

        if (isset($inner['namePrefix'])) {
            $merged['namePrefix'] = ($merged['namePrefix'] ?? '').$inner['namePrefix'];
        }

        if (isset($inner['middleware'])) {
            $outerMiddleware = isset($merged['middleware']) ? (array) $merged['middleware'] : [];
            $innerMiddleware = is_array($inner['middleware']) ? $inner['middleware'] : [$inner['middleware']];
            $merged['middleware'] = array_merge($outerMiddleware, $innerMiddleware);
        }

        return $merged;
    }

    /**
     * Mark the start of a new registration slice for name()/middleware().
     *
     * @return void
     */
    private function beginRegistrationSlice(): void
    {
        $this->registrationSliceStart = count($this->routerArray);
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
            if (!is_array($router['middleware'])) {
                $router['middleware'] = [$router['middleware']];
            }

            foreach ($router['middleware'] as $middleware) {
                /* @var Middleware $middlewareInstance */
                $middlewareInstance = new $middleware();
                $isProcessNext = $middlewareInstance->process($this->request, $this->response);

                if (!$isProcessNext) {
                    $html = $this->getViewHtmlByStatusCode(HttpResponseCode::BAD_REQUEST, "Invalid request");
                    $this->response->errorResponse($html, $middlewareInstance->status_code);

                    return $this;
                }
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
     * Trailing slashes are literal: '/a/' only matches '/a/'.
     * Group urlPrefix joins are normalized (except root '/'), while
     * request URLs are rtrimmed, so prefer slash-less registration.
     *
     * @param $param
     * @param $callback
     * @param $method
     * @param string $name
     * @return void
     */
    private function registerRoute($param, $callback, $method, string $name = ''): void
    {
        if ($param === null || $param === '' || $param === false || $callback === null || $callback === '' || $callback === false) {
            return;
        }

        if (is_array($param) && count($param) === 0) {
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
        if (str_contains($toUrl, '://')) {
            $redirectUrl = $toUrl;
        } else {
            $redirectUrl = self::joinUrl($this->getBaseUrl(), $toUrl);
        }

        foreach (HttpMethod::all() as $methodName) {
            $route = [
                'path' => '/'.ltrim($fromUrl, '/'),
                'redirect' => $redirectUrl,
                'method' => $methodName,
                'code' => $statusCode,
                'name' => '',
            ];

            $groupSettings = $this->getGroupSettings();

            if ($groupSettings) {
                $this->addGroupSettingsToRoute($route, $groupSettings);
            }

            $this->addToRouterArray($route);
        }

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
            $currentMiddleware = isset($route['middleware']) ? (array) $route['middleware'] : [];
            $groupMiddleware = is_array($groupSettings['middleware']) ? $groupSettings['middleware'] : [$groupSettings['middleware']];
            $route['middleware'] = array_merge($currentMiddleware, $groupMiddleware);
        }

        if (isset($groupSettings['urlPrefix'])) {
            $path = self::joinUrl('/'.trim((string) $groupSettings['urlPrefix'], '/'), $route['path']);
            $path = rtrim($path, '/');
            $route['path'] = $path === '' ? '/' : $path;
        }

        if (isset($groupSettings['namePrefix'])) {
            $route['name'] = $groupSettings['namePrefix'].($route['name'] ?? '');
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
    private function addRouteToRouteArray(&$routeArray, $param, $method, $callback, string $name = ''): void
    {
        if (is_string($param) && preg_match('/\{[^}]*\?\}/', $param)) {
            $paramArray = explode('/', $param);
            $size = count($paramArray);

            // Prefix-chain expansion: each optional segment registers the
            // prefix before it (earlier optionals kept in present form),
            // then the full pattern. An empty prefix maps to '/' by design.
            $prefixSegments = [];

            for ($i = 0; $i < $size; $i++) {
                if (preg_match('/\{[^}]*\?\}/', $paramArray[$i])) {
                    $this->appendExpandedRoute($routeArray, $prefixSegments, $method, $callback, $name);
                    $prefixSegments[] = str_replace('?', '', $paramArray[$i]);
                } else {
                    $prefixSegments[] = $paramArray[$i];
                }
            }

            $this->appendExpandedRoute($routeArray, $prefixSegments, $method, $callback, $name);
        } else {
            $this->appendExpandedRoute($routeArray, explode('/', $param), $method, $callback, $name);
        }

    }

    /**
     * Append one expanded route, skipping duplicates within the expansion.
     *
     * @param $routeArray
     * @param $segments
     * @param $method
     * @param $callback
     * @param string $name
     * @return void
     */
    private function appendExpandedRoute(&$routeArray, $segments, $method, $callback, string $name = ''): void
    {
        // $path always starts with '/'; an empty segment list maps to root '/'.
        $path = '/'.ltrim(implode('/', $segments), '/');

        foreach ($routeArray as $existingRoute) {
            if ($existingRoute['path'] === $path && $existingRoute['method'] === $method) {
                return;
            }
        }

        $routeArray[] = [
            'path' => $path,
            'method' => $method,
            'execute' => $callback,
            'name' => $name,
        ];
    }

    /**
     * Adding name to the whole last registration slice
     *
     * @param $string
     * @return $this|bool
     */
    public function name($string): bool|static
    {
        if (count($this->routerArray) == 0) {
            return false;
        }

        foreach ($this->registrationSliceIndexes() as $index) {
            $namePrefix = $this->routerArray[$index]['name'] ?? '';
            $this->routerArray[$index]['name'] = $namePrefix.$string;
        }

        return $this;
    }

    /**
     * Adding middleware to the whole last registration slice
     *
     * @param $middlewareClass
     * @return $this|bool
     */
    public function middleware($middlewareClass): bool|static
    {
        if (count($this->routerArray) == 0) {
            return false;
        }

        foreach ($this->registrationSliceIndexes() as $index) {
            $currentRouterMiddleware = $this->routerArray[$index]['middleware'] ?? null;

            $middlewareList = [];

            if ($currentRouterMiddleware) {
                if (is_array($currentRouterMiddleware)) {
                    foreach ($currentRouterMiddleware as $middleware) {
                        $middlewareList[] = $middleware;
                    }
                } else {
                    $middlewareList[] = $currentRouterMiddleware;
                }
            }

            $middlewareList[] = $middlewareClass;

            $this->routerArray[$index]['middleware'] = $middlewareList;
        }

        return $this;
    }

    /**
     * Indexes of the most recent registration slice.
     *
     * @return array
     */
    private function registrationSliceIndexes(): array
    {
        $total = count($this->routerArray);

        if ($this->registrationSliceStart >= $total) {
            return [];
        }

        $start = max(0, $this->registrationSliceStart);

        return range($start, $total - 1);
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
