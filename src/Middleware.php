<?php
namespace Roolith\Route;

abstract class Middleware
{

    /**
     * Abstract function process
     *
     * @param Request $request
     * @param Response $response
     * @return bool
     */
    abstract protected function process(Request $request, Response $response): bool;
}
