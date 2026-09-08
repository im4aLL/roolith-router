<?php
namespace Roolith\Route\Interfaces;

use Roolith\Route\Request;

/**
 * New-style middleware contract with next() chaining (onion model).
 *
 * Canonical contract: implement this interface. Roolith\Route\NextMiddleware
 * is a thin alias kept for backward compatibility.
 *
 * Implement this for gates that run code before the controller, call
 * $next($request), then optionally post-process the result. Legacy
 * Roolith\Route\Middleware (process(Request, Response): bool) keeps working
 * unchanged; the router runs a mixed chain with legacy entries as gates.
 *
 * Return contract (handled by Router::run()):
 * - false without calling $next: block, router renders blocked body with
 *   $status_code as today. False passed through via return $next($request)
 *   is a handler value and follows Response::body(false) semantics (200 empty).
 * - Roolith\Route\Response instance: emitted directly (status + headers + body).
 *   Only the vendor Response is emitted directly; other objects are JSON.
 * - string: HTML body, array|object: JSON body (Response::body() semantics).
 * - true|null (without calling $next): continue chain automatically.
 * - Return value of $next($request): passthrough, optionally transformed.
 * - Handlers must return values: echoes inside the pipeline are discarded.
 * - Throwable from middleware or handler: single generic 500 via pipeline catch-all.
 */
interface NextMiddlewareInterface
{
    /**
     * Process the request and optionally delegate to the next handler.
     *
     * @param Request $request Current request.
     * @param callable $next Next handler: fn(Request $request): mixed.
     * @return mixed False to block, vendor Response or controller-like value otherwise.
     */
    public function process(Request $request, callable $next): mixed;
}
