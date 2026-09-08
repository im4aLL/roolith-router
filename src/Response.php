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
     * Stored headers for testability and middleware emission.
     *
     * HeaderTrait sends headers via header() immediately; this map keeps a
     * copy so a returned Response can be re-emitted by the router without
     * losing Location / Content-Type / custom headers in CLI tests where
     * header() is a no-op. Keys keep original case, lookups are
     * case-insensitive.
     *
     * @var array<string, string>
     */
    protected array $headers = [];

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
        if ($content instanceof self) {
            $this->applyReturnedResponse($content);
            $output = $content->getLastOutput();

            if ($output !== null && $output !== '') {
                echo $output;
            }

            return $this;
        }

        $output = $this->renderBody($content);

        echo $output;

        return $this;
    }

    /**
     * Render response body without echoing (test/embedding hook).
     *
     * A vendor Response value is unwrapped: its status and stored headers
     * (including Location and Content-Type) are applied with no second
     * Content-Type, and its pre-rendered body is returned verbatim.
     *
     * @param mixed $content
     * @return string
     */
    public function renderBody(mixed $content = ''): string
    {
        if ($content instanceof self) {
            $this->applyReturnedResponse($content);

            $output = $content->getLastOutput() ?? '';
            $this->lastOutput = $output;

            return $output;
        }

        if (is_array($content) || is_object($content)) {
            $output = $this->setHeaderJson()->outputJson($content);
        } else {
            $output = $this->setHeaderHtml()->outputHtml($content);
        }

        $this->lastOutput = is_string($output) ? $output : (string) $output;

        return $this->lastOutput;
    }

    /**
     * Apply a returned Response value (status + headers, no body echo).
     *
     * Shared by body()/renderBody() so controllers returning a vendor
     * Response emit directly in both legacy and next() flows.
     *
     * @param self $returned Returned Response value.
     * @return void
     */
    private function applyReturnedResponse(self $returned): void
    {
        $this->setStatusCode($returned->getStatusCode());

        foreach ($returned->getHeaders() as $name => $value) {
            if (strtolower((string) $name) === 'location') {
                continue;
            }

            $this->setHeader((string) $name, (string) $value);
        }

        $location = $returned->getHeader('Location');

        if ($location !== null) {
            $this->redirect($location);
        }

        $body = $returned->getLastOutput();

        if ($body !== null) {
            $this->lastOutput = $body;
        }
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
            $this->headers['Content-Type'] = 'application/json; charset=UTF-8';
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
            $this->headers['Content-Type'] = 'text/html; charset=UTF-8';
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
            $this->headers['Content-Type'] = 'text/plain; charset=UTF-8';
            $this->hasHeaderContentType = true;
        }

        return $this;
    }

    /**
     * Set an arbitrary header, stored for re-emission and sent when possible.
     *
     * CR/LF is stripped from name and value to block header injection.
     * Setting Content-Type marks the Content-Type flag so a later
     * setHeaderJson/Html does not emit a second Content-Type.
     *
     * @param string $name Header name.
     * @param string $value Header value.
     * @return $this
     */
    public function setHeader(string $name, string $value): static
    {
        $safeName = str_replace(["\r", "\n"], '', trim($name));
        $safeValue = str_replace(["\r", "\n"], '', $value);

        if ($safeName === '') {
            return $this;
        }

        $this->headers[$safeName] = $safeValue;

        if (strtolower($safeName) === 'content-type') {
            $this->hasHeaderContentType = true;
        }

        if (!headers_sent()) {
            header($safeName . ': ' . $safeValue);
        }

        return $this;
    }

    /**
     * Check for a stored header (case-insensitive).
     *
     * @param string $name Header name.
     * @return bool
     */
    public function hasHeader(string $name): bool
    {
        return $this->getHeader($name) !== null;
    }

    /**
     * Get a stored header value (case-insensitive) or null when missing.
     *
     * @param string $name Header name.
     * @return string|null
     */
    public function getHeader(string $name): ?string
    {
        $wanted = strtolower(trim($name));

        foreach ($this->headers as $key => $value) {
            if (strtolower((string) $key) === $wanted) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * Get all stored headers.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Echo a pre-rendered body without touching headers or status.
     *
     * Test/embedding hook kept for backward compatibility; the next()
     * middleware pipeline emits via emitMiddlewareResponse()->body()
     * instead. Updates lastOutput for testability.
     *
     * @param string $body Pre-rendered body string.
     * @return $this
     */
    public function sendRaw(string $body): static
    {
        $this->lastOutput = $body;

        echo $body;

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
    protected function outputJson(mixed $content): bool|string
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
    protected function outputHtml(mixed $content): mixed
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
