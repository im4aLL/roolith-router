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

$router->get(['about', 'test'], function() {
    return ['name' => 'Test bangla char', 'age' => 45];
});

$router->get('get', function() {
    return 'get content';
});

$router->post('post', function() {
    return 'post content';
});

$router->put('put', function() {
    return 'put content';
});

$router->patch('patch', function() {
    return 'patch content';
});

$router->delete('delete', function() {
    return 'delete content';
});

$router->run();

//dd($router->getRouteList());