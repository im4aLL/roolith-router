<?php
namespace Roolith\Route\Traits;

trait UrlJoinTrait
{
    /**
     * Join a base URL and a path with exactly one separator slash.
     * Canonical body used by RouterBase: bare and trailing-slash
     * bases produce identical URLs. Chunk 3 wires this up for Request.
     *
     * @param string $base
     * @param string $path
     * @return string
     */
    protected static function joinUrl(string $base, string $path): string
    {
        return rtrim($base, '/').'/'.ltrim($path, '/');
    }
}
