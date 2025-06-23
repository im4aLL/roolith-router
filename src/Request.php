<?php
namespace Roolith\Route;

use Roolith\Route\HttpConstants\HttpMethod;

class Request
{
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
        $this->requestMethod = $_SERVER['REQUEST_METHOD'] ?? HttpMethod::GET;
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
     * @return string
     */
    public function getRequestedUrl(): string
    {
        $currentUrl = $this->getCurrentUrl();
        $actualUrl = rtrim(str_replace($this->baseUrl, '', $currentUrl), '/');
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
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
            return true;
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
            return true;
        }

        return false;
    }

    /**
     * GET current full URL
     *
     * @return string
     */
    protected function getCurrentUrl(): string
    {
        return ($this->isSecure() ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    }

    /**
     * Sanitize string
     *
     * @param $string
     * @return string|string[]|null
     */
    protected function cleanUrlString($string): array|string|null
    {
        return preg_replace("/[^a-zA-Z0-9-._]+/", "", $string);
    }

    /**
     * Sanitize string for array walk
     *
     * @param $string
     * @return string|string[]|null
     */
    protected function cleanUrlStringArray($string): array|string|null
    {
        if(str_contains($string, '?')) {
            $string = substr($string, 0, strpos($string, '?'));
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
     * @param $paramKey
     * @return string|string[]|null
     */
    public function getUrlParam($paramKey): array|string|null
    {
        return isset($_GET[$paramKey]) ? $this->cleanUrlString($_GET[$paramKey]) : null;
    }
}
