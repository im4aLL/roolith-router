<?php
namespace Demo;

use Roolith\Middleware;
use Roolith\Request;
use Roolith\Response;

class AuthMiddleware extends Middleware
{
    public function process(Request $request, Response $response)
    {
        return true;
    }
}