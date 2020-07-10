<?php
namespace Roolith;

class Request
{
    private $baseUrl;
    private $requestMethod;

    public function __construct()
    {
        $this->requestMethod = $_SERVER['REQUEST_METHOD'];
    }

    public function setBaseUrl($url)
    {
        $this->baseUrl = $url;

        return $this;
    }

    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    public function getRequestMethod()
    {
        return $this->requestMethod;
    }

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

    protected function getCurrentUrl()
    {
        return "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    }

    protected function cleanUrlString($string)
    {
        return preg_replace("/[^a-zA-Z0-9-._]+/", "", $string);
    }

    protected function cleanUrlStringArray($string)
    {
        if(strstr($string, '?')) {
            $string = substr($string, 0, strpos($string, '?'));
        }

        return $this->cleanUrlString($string);
    }
}