<?php
namespace Demo;

use Roolith\Route\Middleware;
use Roolith\Route\Request;
use Roolith\Route\Response;

class AuthMiddleware extends Middleware
{
    public function process(Request $request, Response $response): bool
    {
        return true;
    }
}