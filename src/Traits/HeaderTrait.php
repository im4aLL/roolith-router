<?php
namespace Roolith\Route\Traits;

trait HeaderTrait
{
    /**
     * Set header JSON
     */
    public function makeJsonHeader()
    {
        header('Content-Type: application/json; charset=UTF-8');
    }

    /**
     * Set header HTML
     */
    public function makeHtmlHeader()
    {
        header('Content-Type: text/html; charset=UTF-8');
    }

    /**
     * Set header PLAIN TEXT
     */
    public function makePlainTextHeader()
    {
        header('Content-Type: text/plain; charset=UTF-8');
    }

    /**
     * Redirect to URL
     *
     * @param $url
     */
    public function redirect($url)
    {
        header("Location: $url");
    }
}
