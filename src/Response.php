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
     * Last rendered body output (test hook; echo behavior kept for BC).
     *
     * @var string|null
     */
    protected ?string $lastOutput = null;

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
     * The status is always stored; the SAPI call is skipped when headers
     * were already sent (CLI output, prior echo) so tests and embedded
     * usage do not warn.
     *
     * @param int $code
     * @return $this
     */
    public function setStatusCode(int $code = HttpResponseCode::OK): static
    {
        $this->statusCode = $code;

        if (!headers_sent()) {
            http_response_code($code);
        }

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
     * Echo is kept for backward compatibility with the Roolith framework
     * consumer. The rendered string is also stored and returned via
     * renderBody()/getLastOutput() so embedding code and unit tests do
     * not have to fight output buffering.
     *
     * @param mixed $content
     * @return $this
     */
    public function body(mixed $content = ''): static
    {
        $output = $this->renderBody($content);

        echo $output;

        return $this;
    }

    /**
     * Render response body without echoing (test/embedding hook).
     *
     * @param mixed $content
     * @return string
     */
    public function renderBody(mixed $content = ''): string
    {
        if (is_array($content) || is_object($content)) {
            $output = $this->setHeaderJson()->outputJson($content);
        } else {
            $output = $this->setHeaderHtml()->outputHtml($content);
        }

        $this->lastOutput = is_string($output) ? $output : (string) $output;

        return $this->lastOutput;
    }

    /**
     * Last rendered body output, if any.
     *
     * @return string|null
     */
    public function getLastOutput(): ?string
    {
        return $this->lastOutput;
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
     * Surfaces encoding failures instead of echoing an empty string:
     * throws on json_encode() failure with the underlying error message.
     *
     * @param $content
     * @return false|string
     */
    protected function outputJson($content): bool|string
    {
        $json = json_encode($this->anythingToUtf8($content));

        if ($json === false) {
            throw new \RuntimeException('JSON encoding failed: ' . json_last_error_msg());
        }

        return $json;
    }

    /**
     * HTML content to UTF8 content
     *
     * @param $content
     * @return mixed
     */
    protected function outputHtml($content): mixed
    {
        return $this->anythingToUtf8($content);
    }

    /**
     * Error response
     *
     * @param string $message
     * @param int $statusCode
     * @return $this
     */
    public function errorResponse(string $message = 'Something went wrong', int $statusCode = HttpResponseCode::INTERNAL_SERVER_ERROR): static
    {
        $this->setStatusCode($statusCode)
            ->setHeaderHtml()
            ->body($message);

        return $this;
    }

    /**
     * JSON error response (errorResponse() stays HTML-only for BC).
     *
     * Mirrors the chunk 1 status contract: default 500, explicit 404/403
     * at call sites. $data is JSON-encoded as-is so APIs get a JSON body.
     *
     * @param mixed $data
     * @param int $statusCode
     * @return $this
     */
    public function errorJson(mixed $data = 'Something went wrong', int $statusCode = HttpResponseCode::INTERNAL_SERVER_ERROR): static
    {
        $this->setStatusCode($statusCode)->setHeaderJson();

        $output = $this->outputJson($data);
        $this->lastOutput = is_string($output) ? $output : (string) $output;

        echo $this->lastOutput;

        return $this;
    }
}
