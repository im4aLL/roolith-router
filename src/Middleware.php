<?php
namespace Roolith\Route;

use Roolith\Route\HttpConstants\HttpResponseCode;

abstract class Middleware
{
    public int $status_code = HttpResponseCode::FORBIDDEN;

    /**
     * Abstract function process
     *
     * @param Request $request
     * @param Response $response
     * @return bool
     */
    abstract protected function process(Request $request, Response $response): bool;
}
