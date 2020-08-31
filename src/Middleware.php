<?php
namespace Roolith\Route;

abstract class Middleware
{

    /**
     * Abstract function process
     *
     * @param Request $request
     * @param Response $response
     * @return mixed
     */
    abstract protected function process(Request $request, Response $response);
}
