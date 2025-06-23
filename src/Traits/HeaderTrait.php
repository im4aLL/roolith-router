<?php
namespace Roolith\Route\Traits;

trait HeaderTrait
{
    /**
     * Set header JSON
     */
    public function makeJsonHeader(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
    }

    /**
     * Set header HTML
     */
    public function makeHtmlHeader(): void
    {
        header('Content-Type: text/html; charset=UTF-8');
    }

    /**
     * Set header PLAIN TEXT
     */
    public function makePlainTextHeader(): void
    {
        header('Content-Type: text/plain; charset=UTF-8');
    }

    /**
     * Redirect to URL
     *
     * @param $url
     */
    public function redirect($url): void
    {
        header("Location: $url");
    }
}
