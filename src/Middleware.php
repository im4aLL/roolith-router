<?php
namespace Roolith;

abstract class Middleware
{
    abstract protected function process(Request $request, Response $response);
}