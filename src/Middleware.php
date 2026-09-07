<?php
namespace Roolith\Route;

use Roolith\Route\HttpConstants\HttpResponseCode;

abstract class Middleware
{
    /**
     * Status code sent when process() returns false and blocks the request.
     *
     * Public mutable snake_case is kept for backward compatibility: demos
     * and the Roolith framework consumer set this property directly.
     * Router::run() builds the blocked-response body for this code and
     * sends the same code, so body and status always align.
     */
    public int $status_code = HttpResponseCode::FORBIDDEN;

    /**
     * Abstract function process
     *
     * Declared public (not protected) because Router::run() invokes it
     * from outside the instance; a protected declaration would fatal on
     * any middleware that keeps the narrower visibility.
     *
     * @param Request $request
     * @param Response $response
     * @return bool
     */
    abstract public function process(Request $request, Response $response): bool;
}
