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
    public function __construct(array $settings = [], ?Response $response = null, ?Request $request = null)
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
    public function get(string|array $param, mixed $callback): static
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
    public function post(string|array $param, mixed $callback): static
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
    public function put(string|array $param, mixed $callback): static
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
    public function patch(string|array $param, mixed $callback): static
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
    public function delete(string|array $param, mixed $callback): static
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
    public function options(string|array $param, mixed $callback): static
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
    public function match(array $array, string|array $param, mixed $callback, string $name = ''): static
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
    public function any(string|array $param, mixed $callback, string $name = ''): static
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
    public function crud(string $param, mixed $callback): static
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
    private function crudCallback(mixed $callback, string $methodName): mixed
    {
        if (is_string($callback)) {
            return $callback.'@'.$methodName;
        }

        if (is_array($callback)) {
            if (count($callback) === 1 && isset($callback[0]) && is_string($callback[0])) {
                return [$callback[0], $methodName];
            }

            if (count($callback) === 2 && isset($callback[0], $callback[1]) && is_string($callback[0]) && is_string($callback[1])) {
                return [$callback[0], $methodName];
            }
        }

        return $callback;
    }

    /**
     * Normalize array controller callback to string form
     *
     * @param $callback
     * @return mixed
     */
    private function normalizeControllerCallback(mixed $callback): mixed
    {
        if (!is_array($callback)) {
            return $callback;
        }

        if (is_callable($callback)) {
            return $callback;
        }

        if (count($callback) !== 2) {
            return $callback;
        }

        if (!isset($callback[0], $callback[1])) {
            return $callback;
        }

        if (!is_string($callback[0]) || !is_string($callback[1])) {
            return $callback;
        }

        if ($callback[0] === '' || $callback[1] === '') {
            return '';
        }

        return $callback[0].'@'.$callback[1];
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
    public function redirect(string $fromUrl, string $toUrl, int $statusCode = HttpResponseCode::MOVED_PERMANENTLY): static
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
    public function group(array $settings, callable $callback): static
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
    private function mergeGroupSettings(array $outer, array $inner): array
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
     * Middleware entries are validated (existing class extending Middleware)
     * and resolved via the DI container with a plain-instantiation fallback;
     * an unresolvable entry emits a single 500 response. A blocked request
     * emits a single response whose body is built for the middleware's own
     * status code. Unknown verbs (HEAD/TRACE/custom) never match a route,
     * so they fall through to the 405/404 handling below.
     *
     * @return $this
     */
    public function run(): static
    {
        $methodName = strtoupper((string) $this->request->getRequestMethod());
        $requestedUrl = $this->request->getRequestedUrl();
        $router = $this->getRequestedRouter($requestedUrl, $methodName);

        if (!$router) {
            $allowedMethods = $this->allowedMethodsForPath($requestedUrl);

            if (count($allowedMethods) > 0) {
                $this->respondMethodNotAllowed($allowedMethods);

                return $this;
            }

            $this->executeRouteMethod($router);

            return $this;
        }

        if (isset($router['middleware'])) {
            if (!is_array($router['middleware'])) {
                $router['middleware'] = [$router['middleware']];
            }

            foreach ($router['middleware'] as $middleware) {
                $middlewareInstance = $this->resolveMiddleware($middleware);

                if (!$middlewareInstance instanceof Middleware) {
                    $middlewareName = is_string($middleware) ? $middleware : get_debug_type($middleware);
                    $html = $this->getViewHtmlByStatusCode(HttpResponseCode::INTERNAL_SERVER_ERROR, "Middleware $middlewareName doesn't exist or is invalid");
                    $this->response->errorResponse($html, HttpResponseCode::INTERNAL_SERVER_ERROR);

                    return $this;
                }

                $isProcessNext = null;

                try {
                    $isProcessNext = $middlewareInstance->process($this->request, $this->response);
                } catch (\Throwable $e) {
                    error_log('[Roolith Router] Middleware failed for ' . get_class($middlewareInstance) . ': ' . $this->sanitizeLogDetail($e->getMessage()));
                    $html = $this->getViewHtmlByStatusCode(HttpResponseCode::INTERNAL_SERVER_ERROR, 'Middleware Error');
                    $this->response->errorResponse($html, HttpResponseCode::INTERNAL_SERVER_ERROR);

                    return $this;
                }

                if (!$isProcessNext) {
                    $html = $this->getViewHtmlByStatusCode($middlewareInstance->status_code, "Invalid request");
                    $this->response->errorResponse($html, $middlewareInstance->status_code);

                    return $this;
                }
            }
        }

        $this->executeRouteMethod($router);

        return $this;
    }

    /**
     * Resolve a route middleware entry to an instance.
     *
     * String entries must name an existing Middleware subclass and are
     * resolved via the DI container so constructor dependencies can be
     * injected; a container failure falls back to plain instantiation.
     * Already-instantiated Middleware entries pass through. Returns null
     * when the entry is invalid or cannot be instantiated.
     *
     * @param $middleware
     * @return Middleware|null
     */
    private function resolveMiddleware(mixed $middleware): ?Middleware
    {
        if ($middleware instanceof Middleware) {
            return $middleware;
        }

        if (!is_string($middleware) || !class_exists($middleware) || !is_subclass_of($middleware, Middleware::class)) {
            return null;
        }

        try {
            $instance = $this->container->get($middleware);
        } catch (\Throwable) {
            $instance = null;
        }

        if (!$instance instanceof Middleware) {
            try {
                $instance = new $middleware();
            } catch (\Throwable) {
                return null;
            }
        }

        return $instance instanceof Middleware ? $instance : null;
    }

    /**
     * Methods registered for a path across all known verbs.
     *
     * Side-effect-free by design: probes via a pure pattern check instead
     * of getRequestedRouter(), which would populate request params through
     * matchPattern()->setRequestedParam() as a side effect.
     *
     * @param string $path
     * @return array
     */
    private function allowedMethodsForPath(string $path): array
    {
        $allowedMethods = [];

        foreach (HttpMethod::all() as $methodName) {
            if ($this->hasRouteForMethodPath($methodName, $path)) {
                $allowedMethods[] = $methodName;
            }
        }

        return $allowedMethods;
    }

    /**
     * Check for a route matching method + path without touching request state.
     *
     * Mirrors RouterBase::getRequestedRouter()/matchPattern() matching
     * (exact match or {placeholder} pattern) but never calls
     * setRequestedParam(), so 405 probing leaves getParam() unchanged.
     *
     * @param string $methodName
     * @param string $path
     * @return bool
     */
    private function hasRouteForMethodPath(string $methodName, string $path): bool
    {
        foreach ($this->getRouteList() as $route) {
            if (($route['method'] ?? null) !== $methodName) {
                continue;
            }

            $routePath = $route['path'] ?? null;

            if ($routePath === $path) {
                return true;
            }

            if (is_string($routePath) && str_contains($routePath, '{') && $this->pathMatchesPattern($routePath, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pure pattern check mirroring RouterBase::matchPattern() without side effects.
     *
     * @param string $routerPath
     * @param string $url
     * @return bool
     */
    private function pathMatchesPattern(string $routerPath, string $url): bool
    {
        $pattern = "/{[^}]*}/";
        preg_match_all($pattern, $routerPath, $matches);

        if (count($matches[0]) === 0) {
            return false;
        }

        $routerPattern = implode('\/', array_map(function (string $segment): string {
            $parts = preg_split('/({[^}]*})/', $segment, -1, PREG_SPLIT_DELIM_CAPTURE);
            $built = '';

            foreach ($parts as $part) {
                if (preg_match('/^{[^}]*}$/', $part)) {
                    $built .= '[^\/]+';
                } else {
                    $built .= preg_quote($part, '/');
                }
            }

            return $built;
        }, explode('/', $routerPath)));

        if (!preg_match("/^$routerPattern$/s", $url)) {
            return false;
        }

        return count(explode('/', $url)) === count(explode('/', $routerPath));
    }

    /**
     * Emit a single 405 response with an Allow header.
     *
     * The Allow header send is skipped when headers were already sent
     * (CLI output, prior echo), consistent with the chunk 3 header-safety
     * rule; the allowed methods are always repeated in the body so the
     * contract stays verifiable without a SAPI.
     *
     * @param array $allowedMethods
     * @return void
     */
    private function respondMethodNotAllowed(array $allowedMethods): void
    {
        if (!headers_sent()) {
            header('Allow: ' . implode(', ', $allowedMethods));
        }

        $message = 'Method Not Allowed. Allowed: ' . implode(', ', $allowedMethods);
        $html = $this->getViewHtmlByStatusCode(HttpResponseCode::METHOD_NOT_ALLOWED, $message);
        $this->response->errorResponse($html, HttpResponseCode::METHOD_NOT_ALLOWED);
    }

    /**
     * Get active route
     *
     * @return array
     */
    public function activeRoute(): array
    {
        $methodName = strtoupper((string) $this->request->getRequestMethod());

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
    private function registerRoute(string|array $param, mixed $callback, string $method, string $name = ''): void
    {
        $callback = $this->normalizeControllerCallback($callback);

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
    private function registerRedirectRoute(string $fromUrl, string $toUrl, int $statusCode): void
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
    private function addGroupSettingsToRoute(array &$route, array $groupSettings): void
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
    private function addRouteToRouteArray(array &$routeArray, string|array $param, string $method, mixed $callback, string $name = ''): void
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
    private function appendExpandedRoute(array &$routeArray, array $segments, string $method, mixed $callback, string $name = ''): void
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
    public function name(string $string): bool|static
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
    public function middleware(mixed $middlewareClass): bool|static
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
    private function applySettings(array $settings): void
    {
        if (isset($settings['base_url'])) {
            $this->setBaseUrl($settings['base_url']);
        }

        if (isset($settings['view_dir'])) {
            $this->setViewDir($settings['view_dir']);
        }

        if (isset($settings['use_di'])) {
            $this->setUseDI($settings['use_di']);
        }

    }
}
