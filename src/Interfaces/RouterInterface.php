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
    public function get($param, $callback);

    /**
     * Define POST route
     *
     * @param $param
     * @param $callback
     * @return $this
     */
    public function post($param, $callback);

    /**
     * Define PUT route
     *
     * @param $param
     * @param $callback
     * @return $this
     */
    public function put($param, $callback);

    /**
     * Define PATCH route
     *
     * @param $param
     * @param $callback
     * @return $this
     */
    public function patch($param, $callback);

    /**
     * Define DELETE route
     *
     * @param $param
     * @param $callback
     * @return $this
     */
    public function delete($param, $callback);

    /**
     * Define OPTIONS route
     *
     * @param $param
     * @param $callback
     * @return $this
     */
    public function options($param, $callback);

    /**
     * Define multiple route method as array
     *
     * @param $array
     * @param $param
     * @param $callback
     * @return $this
     */
    public function match($array, $param, $callback);

    /**
     * Defined wildcard route
     *
     * @param $param
     * @param $callback
     * @return $this
     */
    public function any($param, $callback);

    /**
     * Define crud route
     *
     * @param $param
     * @param $callback
     * @return $this
     */
    public function crud($param, $callback);

    /**
     * Define redirect route
     *
     * @param $fromUrl
     * @param $toUrl
     * @param int $statusCode
     * @return $this
     */
    public function redirect($fromUrl, $toUrl, $statusCode = HttpResponseCode::MOVED_PERMANENTLY);

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
    public function run();

    /**
     * Adding name to last route item
     *
     * @param $string
     * @return $this|bool
     */
    public function name($string);

    /**
     * Adding middleware to last route item
     *
     * @param $middlewareClass
     * @return $this|bool
     */
    public function middleware($middlewareClass);
}
