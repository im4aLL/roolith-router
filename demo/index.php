<?php
use Roolith\Router;

require_once __DIR__ . '/../vendor/autoload.php';

function dd($var) {
    echo '<pre>';
    print_r($var);
    echo '</pre>';
}

$router = new Router();
echo $router->test();