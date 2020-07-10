<?php
namespace Roolith;

use Roolith\HttpConstants\HttpResponseCode;
use Roolith\Traits\EncoderTrait;
use Roolith\Traits\HeaderTrait;

class Response
{
    use HeaderTrait, EncoderTrait;

    protected $statusCode;
    protected $hasHeaderContentType;

    public function __construct() {
        $this->hasHeaderContentType = false;
    }

    public function setStatusCode($code = HttpResponseCode::OK)
    {
        $this->statusCode = $code;
        http_response_code($code);

        return $this;
    }

    public function body($content = '')
    {
        if (!$this->statusCode) {
            $this->setStatusCode(HttpResponseCode::OK);
        }

        if (is_array($content) || is_object($content)) {
            echo $this->setHeaderJson()->outputJson($content);
        } else {
            echo $this->setHeaderHtml()->outputHtml($content);
        }
    }

    public function setHeaderJson()
    {
        if (!$this->hasHeaderContentType) {
            $this->makeJsonHeader();
            $this->hasHeaderContentType = true;
        }

        return $this;
    }

    public function setHeaderHtml()
    {
        if (!$this->hasHeaderContentType) {
            $this->makeHtmlHeader();
            $this->hasHeaderContentType = true;
        }

        return $this;
    }

    public function setHeaderPlain()
    {
        if (!$this->hasHeaderContentType) {
            $this->makePlainTextHeader();
            $this->hasHeaderContentType = true;
        }

        return $this;
    }

    protected function outputJson($content)
    {
        return json_encode($this->anythingToUtf8($content));
    }

    protected function outputHtml($content)
    {
        return $this->anythingToUtf8($content);
    }

    public function errorResponse($message = 'Something went wrong')
    {
        $this->setStatusCode(HttpResponseCode::NOT_FOUND)
            ->setHeaderPlain()
            ->body($message);
    }
}