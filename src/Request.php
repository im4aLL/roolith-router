<?php
namespace Roolith;

use Roolith\HttpConstants\HttpMethod;

class Request
{
    /**
     * Base URL
     *
     * @var string
     */
    private $baseUrl;

    /**
     * Current request method name
     *
     * @var mixed
     */
    private $requestMethod;

    /**
     * Current router pattern matched key value pair
     *
     * @var array
     */
    private $requestedParam;

    /**
     * Request constructor.
     */
    public function __construct()
    {
        $this->requestMethod = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : HttpMethod::GET;
        $this->requestedParam = [];
    }

    /**
     * Set base URL
     *
     * @param $url
     * @return $this
     */
    public function setBaseUrl($url)
    {
        $this->baseUrl = $url;

        return $this;
    }

    /**
     * Get base URL
     *
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    /**
     * Get current request method
     *
     * @return mixed
     */
    public function getRequestMethod()
    {
        return $this->requestMethod;
    }

    /**
     * Get requested URL without base URL
     *
     * @return string
     */
    public function getRequestedUrl()
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
     * GET current full URL
     *
     * @return string
     */
    protected function getCurrentUrl()
    {
        return "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    }

    /**
     * Sanitize string
     *
     * @param $string
     * @return string|string[]|null
     */
    protected function cleanUrlString($string)
    {
        return preg_replace("/[^a-zA-Z0-9-._]+/", "", $string);
    }

    /**
     * Sanitize string for array walk
     *
     * @param $string
     * @return string|string[]|null
     */
    protected function cleanUrlStringArray($string)
    {
        if(strstr($string, '?')) {
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
    public function setRequestedParam($paramArray, $paramValueArray)
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
    public function getParam($paramKey)
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
    public function getUrlParam($paramKey)
    {
        return isset($_GET[$paramKey]) ? $this->cleanUrlString($_GET[$paramKey]) : null;
    }
}
