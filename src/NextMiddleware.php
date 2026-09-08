<?php
namespace Roolith\Route;

use Roolith\Route\Interfaces\NextMiddlewareInterface;

/**
 * Thin alias for the new-style middleware contract (kept for BC).
 *
 * Canonical contract is Roolith\Route\Interfaces\NextMiddlewareInterface;
 * implement that for new code. This alias extends the canonical interface
 * so a single instanceof check covers both names.
 *
 * Example:
 *   final class AuthMiddleware implements NextMiddlewareInterface
 *   {
 *       public function process(Request $request, callable $next): mixed
 *       {
 *           return $next($request);
 *       }
 *   }
 */
interface NextMiddleware extends NextMiddlewareInterface
{
}
