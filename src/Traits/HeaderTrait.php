<?php
namespace Roolith\Traits;

trait HeaderTrait
{
    public function makeJsonHeader()
    {
        header('Content-Type: application/json; charset=UTF-8');
    }

    public function makeHtmlHeader()
    {
        header('Content-Type: text/html; charset=UTF-8');
    }

    public function makePlainTextHeader()
    {
        header('Content-Type: text/plain; charset=UTF-8');
    }

    public function redirect($url)
    {
        header("Location: $url");
    }
}