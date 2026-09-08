<?php
namespace Roolith\Route\Traits;

trait HeaderTrait
{
    /**
     * Set header JSON
     */
    public function makeJsonHeader(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
        }
    }

    /**
     * Set header HTML
     */
    public function makeHtmlHeader(): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
    }

    /**
     * Set header PLAIN TEXT
     */
    public function makePlainTextHeader(): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=UTF-8');
        }
    }

    /**
     * Redirect to URL
     *
     * Strips CR/LF characters from $url to block header injection via a
     * crafted redirect target. Does NOT exit: execution continues after
     * sending the Location header, so callers needing termination must
     * handle it (long-running contexts). Skipped when headers already
     * sent (CLI/tests) to stay warning-free.
     *
     * @param mixed $url
     */
    public function redirect(mixed $url): void
    {
        $safeUrl = str_replace(["\r", "\n"], '', (string) $url);

        if (property_exists($this, 'headers')) {
            $this->headers['Location'] = $safeUrl;
        }

        if (!headers_sent()) {
            header("Location: $safeUrl");
        }
    }
}
