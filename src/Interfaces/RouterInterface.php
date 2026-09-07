<?php

namespace Roolith\Route\Interfaces;

use Roolith\Route\HttpConstants\HttpResponseCode;

interface RouterInterface
{
    /**
     * Define GET route
     *
     * @param $param
     * @param $callback
     * @return $this
     */
    public function get(string|array $param, mixed $callback): static;

    /**
     * Define POST route
     *
     * @param $param
     * @param $callback
     * @return $this
     */
    public function post(string|array $param, mixed $callback): static;

    /**
     * Define PUT route
     *
     * @param $param
     * @param $callback
     * @return $this
     */
    public function put(string|array $param, mixed $callback): static;

    /**
     * Define PATCH route
     *
     * @param $param
     * @param $callback
     * @return $this
     */
    public function patch(string|array $param, mixed $callback): static;

    /**
     * Define DELETE route
     *
     * @param $param
     * @param $callback
     * @return $this
     */
    public function delete(string|array $param, mixed $callback): static;

    /**
     * Define OPTIONS route
     *
     * @param $param
     * @param $callback
     * @return $this
     */
    public function options(string|array $param, mixed $callback): static;

    /**
     * Define multiple route method as array
     *
     * @param $array
     * @param $param
     * @param $callback
     * @return $this
     */
    public function match(array $array, string|array $param, mixed $callback, string $name = ''): static;

    /**
     * Defined wildcard route
     *
     * @param $param
     * @param $callback
     * @return $this
     */
    public function any(string|array $param, mixed $callback, string $name = ''): static;

    /**
     * Define crud route
     *
     * @param $param
     * @param $callback
     * @return $this
     */
    public function crud(string $param, mixed $callback): static;

    /**
     * Define redirect route
     *
     * @param $fromUrl
     * @param $toUrl
     * @param int $statusCode
     * @return $this
     */
    public function redirect(string $fromUrl, string $toUrl, int $statusCode = HttpResponseCode::MOVED_PERMANENTLY): static;

    /**
     * Define group for routes
     *
     * @param $settings
     * @param $callback
     */
    public function group(array $settings, callable $callback): static;

    /**
     * Match requested URL with route list and execute it's callable method
     *
     * @return $this
     */
    public function run(): static;

    /**
     * Adding name to last route item
     *
     * @param $string
     * @return $this|bool
     */
    public function name(string $string): bool|static;

    /**
     * Adding middleware to last route item
     *
     * @param $middlewareClass
     * @return $this|bool
     */
    public function middleware(mixed $middlewareClass): bool|static;
}
