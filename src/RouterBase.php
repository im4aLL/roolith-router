<?php
namespace Roolith\Route;

use DI\Container;
use Roolith\Route\HttpConstants\HttpResponseCode;
use Roolith\Route\Traits\UrlJoinTrait;

abstract class RouterBase
{
    use UrlJoinTrait;
    /**
     * List of all routes
     *
     * @var array
     */
    protected array $routerArray;

    /**
     * Response class instance
     *
     * @var Response
     */
    protected Response $response;

    /**
     * Request class instance
     *
     * @var Request
     */
    protected Request $request;

    /**
     * Group route settings value
     *
     * @var array
     */
    protected array $groupSettings;

    /**
     * @var ?string
     */
    protected ?string $viewDir;

    /**
     * @var Container Dependency injection container
     */
    protected Container $container;

    /**
     * @var bool whether you use dependency injection or not
     */
    protected bool $use_di = true;


    public function __construct(Response $response, Request $request)
    {
        $this->routerArray = [];
        $this->response = $response;
        $this->request = $request;
        $this->groupSettings = [];
        $this->viewDir = null;

        $this->container = new Container();
    }

    /**
     * Set base url to request
     *
     * @param $url
     * @return $this
     */
    public function setBaseUrl($url): static
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
    public function setViewDir($dir): static
    {
        $this->viewDir = $dir;

        return $this;
    }

    public function setUseDI(bool $useDI): static
    {
        $this->use_di = $useDI;

        return $this;
    }

    /**
     * Get base url from request
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->request->getBaseUrl();
    }

    /**
     * Get current group router settings
     *
     * @return array|bool
     */
    public function getGroupSettings(): bool|array
    {
        return $this->groupSettings && count($this->groupSettings) > 0 ? $this->groupSettings : false;
    }

    /**
     * Set current group router settings
     *
     * @param $groupSettings
     * @return $this
     */
    public function setGroupSettings($groupSettings): static
    {
        $this->groupSettings = $groupSettings;

        return $this;
    }

    /**
     * Reset current group settings
     *
     * @return $this
     */
    public function resetGroupSettings(): static
    {
        $this->groupSettings = [];

        return $this;
    }

    /**
     * Execute router callback method
     *
     * Redirect dispatch is no-exit by design: the Location header is sent
     * (CRLF-sanitized, skipped when headers were already sent per chunk 3)
     * and execution continues, so callers needing termination must handle
     * it themselves. Kept as `return $this` for backward compatibility.
     *
     * @param $router
     * @return $this
     */
    protected function executeRouteMethod($router): static
    {
        if (!$router) {
            $this->response->errorResponse($this->getViewHtmlByStatusCode(HttpResponseCode::NOT_FOUND, "Route doesn't exists"), HttpResponseCode::NOT_FOUND);
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
            $controllerReference = $router['execute'];

            if (!str_contains($controllerReference, '@')) {
                $this->response->errorResponse($this->getViewHtmlByStatusCode(HttpResponseCode::INTERNAL_SERVER_ERROR, "Invalid controller reference '$controllerReference' (expected 'Class@method')"), HttpResponseCode::INTERNAL_SERVER_ERROR);

                return $this;
            }

            [$className, $classMethodName] = explode('@', $controllerReference, 2);

            if (!class_exists($className)) {
                $this->response->errorResponse($this->getViewHtmlByStatusCode(HttpResponseCode::NOT_FOUND, "Class $className doesn't exist"), HttpResponseCode::NOT_FOUND);

                return $this;
            }

            if (!method_exists($className, $classMethodName)) {
                $this->response->errorResponse($this->getViewHtmlByStatusCode(HttpResponseCode::NOT_FOUND, "$classMethodName method doesn't exist in $className"), HttpResponseCode::NOT_FOUND);

                return $this;
            }

            if ($this->use_di) {
                $this->executeRouteMethodClassDI($className, $classMethodName, $router);
            } else {
                $this->executeRouteMethodClassLegacy($className, $classMethodName, $router);
            }
        } else {
            // Residual type (array, int, null, or missing key): neither
            // callable nor 'Class@method' string. Emit one generic 500
            // instead of silently returning 200 with no body.
            $this->response->errorResponse($this->getViewHtmlByStatusCode(HttpResponseCode::INTERNAL_SERVER_ERROR, 'Invalid route handler'), HttpResponseCode::INTERNAL_SERVER_ERROR);
        }

        return $this;
    }

    /**
     * Sanitize exception text for a single error_log line.
     *
     * Strips CR/LF to block log forging and truncates to ~500 chars to
     * avoid leaking long paths while keeping enough context to debug.
     *
     * @param string $detail
     * @return string
     */
    protected function sanitizeLogDetail(string $detail): string
    {
        $safe = str_replace(["\r", "\n"], ' ', $detail);

        return mb_substr($safe, 0, 500);
    }

    /**
     * Invoke class method with dependency injection
     *
     * Each failure path emits exactly one response and returns: the raw
     * exception message is logged (not echoed) and the client gets a
     * generic 500 body per the chunk 1 status contract.
     *
     * @param $className string
     * @param $classMethodName string
     * @param $router
     * @return void
     */
    private function executeRouteMethodClassDI(string $className, string $classMethodName, $router): void
    {
        try {
            $classDI = $this->container->get($className);
        } catch (\Throwable $e) {
            error_log('[Roolith Router] Dependency injection failed for ' . $className . '@' . $classMethodName . ': ' . $this->sanitizeLogDetail($e->getMessage()));
            $this->response->errorResponse($this->getViewHtmlByStatusCode(HttpResponseCode::INTERNAL_SERVER_ERROR, 'Dependency Injection Error On ' . $className . ' ' . $classMethodName), HttpResponseCode::INTERNAL_SERVER_ERROR);

            return;
        }

        if (!isset($classDI)) {
            $this->response->errorResponse($this->getViewHtmlByStatusCode(HttpResponseCode::INTERNAL_SERVER_ERROR, 'Dependency Injection Error On ' . $className . ' ' . $classMethodName), HttpResponseCode::INTERNAL_SERVER_ERROR);

            return;
        }

        $content = isset($router['payload']) ? call_user_func_array([$classDI, $classMethodName], $router['payload']) : call_user_func([$classDI, $classMethodName]);
        $this->response->body($content);
    }

    /**
     * Invoke class method in tradition way
     *
     * Plain instantiation fatals on constructor dependencies, so it is
     * wrapped: an uninstantiable class emits a single 500 response and
     * returns instead of bubbling an Error.
     *
     * @param $className string
     * @param $classMethodName string
     * @param $router
     * @return void
     */
    private function executeRouteMethodClassLegacy(string $className, string $classMethodName, $router): void
    {
        try {
            $instance = new $className();
        } catch (\Throwable $e) {
            error_log('[Roolith Router] Legacy controller instantiation failed for ' . $className . '@' . $classMethodName . ': ' . $this->sanitizeLogDetail($e->getMessage()));
            $this->response->errorResponse($this->getViewHtmlByStatusCode(HttpResponseCode::INTERNAL_SERVER_ERROR, 'Controller Error On ' . $className . ' ' . $classMethodName), HttpResponseCode::INTERNAL_SERVER_ERROR);

            return;
        }

        $content = isset($router['payload']) ? call_user_func_array([$instance, $classMethodName], $router['payload']) : call_user_func([$instance, $classMethodName]);
        $this->response->body($content);
    }

    /**
     * Match requested url with a router pattern
     *
     * @param $path
     * @param $method
     * @return mixed|null
     */
    protected function getRequestedRouter($path, $method): mixed
    {
        $selectedRoute = null;

        foreach ($this->routerArray as $route) {
            if ($route['method'] == $method) {
                if ($route['path'] == $path) {
                    $selectedRoute = $route;
                    break;
                } elseif (str_contains($route['path'], '{')) {
                    $patternValue = $this->matchPattern($route['path'], $path);

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
     * Route match method with regex
     *
     * @param $routerPath
     * @param $url
     * @return array|bool
     */
    protected function matchPattern($routerPath, $url): bool|array
    {
        $result = false;

        $pattern = "/{[^}]*}/";
        preg_match_all($pattern, $routerPath, $matches);
        $matchArray = $matches[0];

        if (count($matchArray) == 0) {
            return false;
        }

        $routerPattern = implode('\/', array_map(function ($segment) {
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
        $actualRouterPattern = "/^$routerPattern$/s";
        preg_match($actualRouterPattern, $url, $patternMatch);

        if (count($patternMatch) == 0) {
            return false;
        }

        $valueArray = [];

        $routerPathArray = explode('/', $routerPath);
        $routerPathArraySize = count($routerPathArray);
        $urlArray = explode('/', $url);

        if (count($urlArray) !== $routerPathArraySize) {
            return false;
        }

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
    protected function addToRouterArray($route): static
    {
        $this->routerArray[] = $route;

        return $this;
    }

    /**
     * Get list of registered router
     *
     * @return array
     */
    public function getRouteList(): array
    {
        return $this->routerArray;
    }

    /**
     * Get registered routes as a terminal-friendly ASCII table.
     *
     * Usage: echo $router->formattedRouteList();
     *
     * @return string
     */
    public function formattedRouteList(): string
    {
        return RouteTableRenderer::render($this->routerArray);
    }

    /**
     * Get full URL by router name
     *
     * First-match-wins on duplicate names. Placeholders are replaced with
     * urlencode()d values; '{key?}' is tolerated as '{key}'. Not-found
     * returns the base URL unchanged (pinned for BC).
     *
     * @param $string
     * @param $settings array
     * @return string
     */
    public function getUrlByName($string, array $settings = []): string
    {
        $url = '';

        foreach ($this->routerArray as $route) {
            if ($route['name'] == $string) {
                $url = $route['path'];
                break;
            }
        }

        if ($settings && count($settings) > 0) {
            $patternFindArray = [];
            $patternReplaceArray = [];

            foreach ($settings as $key => $value) {
                $patternFindArray[] = '/\\{' . preg_quote((string) $key, '/') . '\\??\\}/';
                $patternReplaceArray[] = urlencode((string) $value);
            }

            $replaced = preg_replace($patternFindArray, $patternReplaceArray, $url);
            $url = is_string($replaced) ? $replaced : $url;
        }

        if ($url === '') {
            return $this->getBaseUrl();
        }

        return self::joinUrl($this->getBaseUrl(), $url);
    }

    /**
     * Get view html by status code
     *
     * View contract: the included file runs in an isolated static closure
     * scope with exactly two variables available - $statusCode (the looked
     * up code) and $message (the fallback text, returned as-is when no
     * view file exists). The view file path is passed as an extra
     * argument and read via func_get_arg() so no $filePath variable
     * leaks into view scope. $this is unbound (static closure). Only
     * `<code>.php` files under the configured view dir are ever
     * included; anything else falls back to $message. Output buffering
     * is always released via try/finally.
     *
     * @param $statusCode
     * @param string $message
     * @return string
     */
    public function getViewHtmlByStatusCode($statusCode, string $message = ''): string
    {
        if (!$this->viewDir) {
            return $message;
        }

        $filePath = $this->viewDir . '/' . $statusCode . '.php';
        if (file_exists($filePath)) {
            $renderView = static function ($statusCode, string $message): void {
                include func_get_arg(2);
            };
            ob_start();
            try {
                $renderView($statusCode, $message, $filePath);
            } finally {
                $output = ob_get_clean();
            }

            return is_string($output) ? $output : $message;
        }

        return $message;
    }
}
