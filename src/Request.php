<?php
namespace Roolith\Route;

use Roolith\Route\HttpConstants\HttpMethod;
use Roolith\Route\Traits\UrlJoinTrait;

class Request
{
    use UrlJoinTrait;
    /**
     * Base URL
     *
     * @var string
     */
    private string $baseUrl = '';

    /**
     * Current request method name
     *
     * @var mixed
     */
    private mixed $requestMethod;

    /**
     * Current router pattern matched key value pair
     *
     * @var array
     */
    private array $requestedParam;

    /**
     * Request constructor.
     */
    public function __construct()
    {
        $this->requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? HttpMethod::GET);
        $this->requestedParam = [];
    }

    /**
     * Set base URL
     *
     * @param $url
     * @return $this
     */
    public function setBaseUrl($url): static
    {
        $this->baseUrl = $url;

        return $this;
    }

    /**
     * Get base URL
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Get current request method
     *
     * @return mixed
     */
    public function getRequestMethod(): mixed
    {
        return $this->requestMethod;
    }

    /**
     * Get requested URL without base URL
     *
     * Strips the configured base URL only when it is a leading prefix
     * (trailing slashes normalized on both sides), so a base string
     * reappearing later in the path or query does not corrupt routing.
     *
     * @return string
     */
    public function getRequestedUrl(): string
    {
        $currentUrl = $this->getCurrentUrl();
        $normalizedBase = rtrim($this->baseUrl, '/');

        if ($normalizedBase !== '' && str_starts_with($currentUrl, $normalizedBase)) {
            $actualUrl = substr($currentUrl, strlen($normalizedBase));
        } else {
            $actualUrl = $currentUrl;

            if (str_contains($actualUrl, '://')) {
                $parts = parse_url($actualUrl);
                $actualUrl = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
            }
        }

        $actualUrl = rtrim($actualUrl, '/');
        $actualUrl = ltrim($actualUrl, '/');

        $actualUrlArray = explode('/', $actualUrl);
        $actualUrlArray = array_map([$this, 'cleanUrlStringArray'], $actualUrlArray);
        $actualUrlArray = array_filter($actualUrlArray, 'strlen');

        return count($actualUrlArray) > 0 ? '/'.implode('/', $actualUrlArray) : '/';
    }

    /**
     * Check for https
     *
     * @return bool
     */
    protected function isSecure(): bool
    {
        $https = $_SERVER['HTTPS'] ?? null;

        if (isset($https) && ($https === 'on' || $https === '1' || $https === 1 || $https === true)) {
            return true;
        }

        $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null;
        $forwardedSsl = $_SERVER['HTTP_X_FORWARDED_SSL'] ?? null;

        if ((!empty($forwardedProto) && $forwardedProto === 'https') || (!empty($forwardedSsl) && $forwardedSsl === 'on')) {
            return true;
        }

        return false;
    }

    /**
     * GET current full URL
     *
     * Guards $_SERVER access for CLI/test environments without HTTP_HOST
     * or REQUEST_URI. When the Host header is unavailable the configured
     * base URL is preferred so getRequestedUrl() safely resolves to '/'.
     *
     * @return string
     */
    protected function getCurrentUrl(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? null;
        $requestUri = $_SERVER['REQUEST_URI'] ?? null;

        if ($host === null || $host === '' || $requestUri === null) {
            if ($this->baseUrl !== '') {
                return $this->baseUrl;
            }

            $host = ($host === null || $host === '') ? 'localhost' : $host;
            $requestUri = $requestUri ?? '/';
        }

        return ($this->isSecure() ? "https" : "http") . "://" . $host . $requestUri;
    }

    /**
     * Sanitize a URL path segment.
     *
     * Allows RFC 3986 pchar subset (unreserved plus pct-encoded, ':' and
     * '@') widened with unicode letters/numbers so slugs, filenames with
     * dots, and decimals survive. Consistent with the chunk 2 matcher
     * class [^/]+: everything the matcher accepts in a segment stays
     * intact here except '?'/'#' delimiters and control characters, which
     * are handled per-context in cleanUrlStringArray().
     *
     * @param $string
     * @return string|string[]|null
     */
    protected function cleanUrlString($string): array|string|null
    {
        return preg_replace("/[^a-zA-Z0-9\\-._~%+:,@\\p{L}\\p{N}]+/u", "", $string);
    }

    /**
     * Sanitize string for array walk
     *
     * Strips query ('?...') and fragment ('#...') suffixes before applying
     * the path-segment filter.
     *
     * @param $string
     * @return string|string[]|null
     */
    protected function cleanUrlStringArray($string): array|string|null
    {
        $queryPos = strpos($string, '?');

        if ($queryPos !== false) {
            $string = substr($string, 0, $queryPos);
        }

        $fragmentPos = strpos($string, '#');

        if ($fragmentPos !== false) {
            $string = substr($string, 0, $fragmentPos);
        }

        return $this->cleanUrlString($string);
    }

    /**
     * Set requested param array
     *
     * @param $paramArray
     * @param $paramValueArray
     * @return Request
     */
    public function setRequestedParam($paramArray, $paramValueArray): static
    {
        $size = count($paramArray);

        for ($i = 0; $i < $size; $i++) {
            $param = str_replace(['{', '}'], '', $paramArray[$i]);
            $this->requestedParam[$param] = $paramValueArray[$i];
        }

        return $this;
    }

    /**
     * Get requested param value by key
     *
     * @param $paramKey
     * @return bool|mixed
     */
    public function getParam($paramKey): mixed
    {
        if (isset($this->requestedParam[$paramKey])) {
            return $this->requestedParam[$paramKey];
        }

        return false;
    }

    /**
     * Get URL param by key
     *
     * Uses a per-context query-value filter: same base set as path
     * segments plus spaces (decoded query values commonly contain them).
     *
     * @param $paramKey
     * @return string|string[]|null
     */
    public function getUrlParam($paramKey): array|string|null
    {
        return isset($_GET[$paramKey]) ? $this->cleanQueryValue($_GET[$paramKey]) : null;
    }

    /**
     * Sanitize a query-string value (per-context, wider than path filter).
     *
     * @param $string
     * @return string|string[]|null
     */
    protected function cleanQueryValue($string): array|string|null
    {
        if (!is_string($string)) {
            return $string;
        }

        return preg_replace("/[^a-zA-Z0-9\\-._~%+:,@ \\p{L}\\p{N}]+/u", "", $string);
    }
}
