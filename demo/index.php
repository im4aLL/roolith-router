<?php
use Roolith\Router;

require_once __DIR__ . '/../vendor/autoload.php';

function dd($var) {
    echo '<pre>';
    print_r($var);
    echo '</pre>';
}

$router = new Router();
$router->setBaseUrl('http://localhost/router/demo/');

$router->get('about', function() {
    echo 'about page!';
});

$router->get('contact', function() {
    echo 'contact page!';
});

$router->run();

//dd($router->getRouteList());