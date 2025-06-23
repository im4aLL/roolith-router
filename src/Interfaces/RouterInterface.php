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
    public function get($param, $callback): static;

    /**
     * Define POST route
     *
     * @param $param
     * @param $callback
     * @return $this
     */
    public function post($param, $callback): static;

    /**
     * Define PUT route
     *
     * @param $param
     * @param $callback
     * @return $this
     */
    public function put($param, $callback): static;

    /**
     * Define PATCH route
     *
     * @param $param
     * @param $callback
     * @return $this
     */
    public function patch($param, $callback): static;

    /**
     * Define DELETE route
     *
     * @param $param
     * @param $callback
     * @return $this
     */
    public function delete($param, $callback): static;

    /**
     * Define OPTIONS route
     *
     * @param $param
     * @param $callback
     * @return $this
     */
    public function options($param, $callback): static;

    /**
     * Define multiple route method as array
     *
     * @param $array
     * @param $param
     * @param $callback
     * @return $this
     */
    public function match($array, $param, $callback): static;

    /**
     * Defined wildcard route
     *
     * @param $param
     * @param $callback
     * @return $this
     */
    public function any($param, $callback): static;

    /**
     * Define crud route
     *
     * @param $param
     * @param $callback
     * @return $this
     */
    public function crud($param, $callback): static;

    /**
     * Define redirect route
     *
     * @param $fromUrl
     * @param $toUrl
     * @param int $statusCode
     * @return $this
     */
    public function redirect($fromUrl, $toUrl, int $statusCode = HttpResponseCode::MOVED_PERMANENTLY): static;

    /**
     * Define group for routes
     *
     * @param $settings
     * @param $callback
     */
    public function group($settings, $callback);

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
    public function name($string): bool|static;

    /**
     * Adding middleware to last route item
     *
     * @param $middlewareClass
     * @return $this|bool
     */
    public function middleware($middlewareClass): bool|static;
}
