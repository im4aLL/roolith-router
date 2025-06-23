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
    protected int $statusCode = HttpResponseCode::OK;

    /**
     * If header content type already set
     *
     * @var bool
     */
    protected bool $hasHeaderContentType;

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
    public function hasHeaderContentType(): bool
    {
        return $this->hasHeaderContentType;
    }

    /**
     * Set HTTP response status
     *
     * @param int $code
     * @return $this
     */
    public function setStatusCode(int $code = HttpResponseCode::OK): static
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
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Show response body
     *
     * @param mixed $content
     * @return $this
     */
    public function body(mixed $content = ''): static
    {
        if (!$this->getStatusCode()) {
            $this->setStatusCode();
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
    public function setHeaderJson(): static
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
    public function setHeaderHtml(): static
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
    public function setHeaderPlain(): static
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
    protected function outputJson($content): bool|string
    {
        return json_encode($this->anythingToUtf8($content));
    }

    /**
     * HTML content to UTF8 content
     *
     * @param $content
     * @return array|string
     */
    protected function outputHtml($content): array|string
    {
        return $this->anythingToUtf8($content);
    }

    /**
     * Error response
     *
     * @param string $message
     * @return $this
     */
    public function errorResponse(string $message = 'Something went wrong'): static
    {
        $this->setStatusCode(HttpResponseCode::NOT_FOUND)
            ->setHeaderHtml()
            ->body($message);

        return $this;
    }
}
