<?php
namespace Demo;

use Roolith\Route\HttpConstants\HttpResponseCode;
use Roolith\Route\Middleware;
use Roolith\Route\Request;
use Roolith\Route\Response;

class AuthMiddleware extends Middleware
{
    public int $status_code = HttpResponseCode::UNAUTHORIZED;

    public function process(Request $request, Response $response): bool
    {
        return true;
    }
}
