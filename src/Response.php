<?php
namespace Roolith\Route;

use Roolith\Route\HttpConstants\HttpResponseCode;
use Roolith\Route\Traits\EncoderTrait;
use Roolith\Route\Traits\HeaderTrait;

class Response
{
    use HeaderTrait, EncoderTrait;

    /**
     * HTTP status code
     *
     * @var int
     */
    protected $statusCode;

    /**
     * If header content type already set
     *
     * @var bool
     */
    protected $hasHeaderContentType;

    /**
     * Response constructor.
     */
    public function __construct()
    {
        $this->hasHeaderContentType = false;
    }

    /**
     * If header content type already set
     *
     * @return bool
     */
    public function hasHeaderContentType()
    {
        return $this->hasHeaderContentType;
    }

    /**
     * Set HTTP response status
     *
     * @param int $code
     * @return $this
     */
    public function setStatusCode($code = HttpResponseCode::OK)
    {
        $this->statusCode = $code;
        http_response_code($code);

        return $this;
    }

    /**
     * Get status code
     *
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * Show response body
     *
     * @param string $content
     * @return $this
     */
    public function body($content = '')
    {
        if (!$this->getStatusCode()) {
            $this->setStatusCode(HttpResponseCode::OK);
        }

        if (is_array($content) || is_object($content)) {
            echo $this->setHeaderJson()->outputJson($content);
        } else {
            echo $this->setHeaderHtml()->outputHtml($content);
        }

        return $this;
    }

    /**
     * Set content type JSON for header
     *
     * @return $this
     */
    public function setHeaderJson()
    {
        if (!$this->hasHeaderContentType()) {
            $this->makeJsonHeader();
            $this->hasHeaderContentType = true;
        }

        return $this;
    }

    /**
     * Set content type HTML for header
     *
     * @return $this
     */
    public function setHeaderHtml()
    {
        if (!$this->hasHeaderContentType()) {
            $this->makeHtmlHeader();
            $this->hasHeaderContentType = true;
        }

        return $this;
    }

    /**
     * Set content type PLAIN TEXT for header
     *
     * @return $this
     */
    public function setHeaderPlain()
    {
        if (!$this->hasHeaderContentType()) {
            $this->makePlainTextHeader();
            $this->hasHeaderContentType = true;
        }

        return $this;
    }

    /**
     * Array or Object to JSON
     *
     * @param $content
     * @return false|string
     */
    protected function outputJson($content)
    {
        return json_encode($this->anythingToUtf8($content));
    }

    /**
     * HTML content to UTF8 content
     *
     * @param $content
     * @return array|string
     */
    protected function outputHtml($content)
    {
        return $this->anythingToUtf8($content);
    }

    /**
     * Error response
     *
     * @param string $message
     * @return $this
     */
    public function errorResponse($message = 'Something went wrong')
    {
        $this->setStatusCode(HttpResponseCode::NOT_FOUND)
            ->setHeaderHtml()
            ->body($message);

        return $this;
    }
}
