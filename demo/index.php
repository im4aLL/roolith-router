<?php
use Roolith\Router;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/AuthMiddleware.php';

function dd($var) {
    echo '<pre>';
    print_r($var);
    echo '</pre>';
}

$router = new Router();
$router->setBaseUrl('http://localhost/router/demo/');

$router->get('/', function() {
    return 'default. Server request method:'. $_SERVER['REQUEST_METHOD'];
});
$router->get('test/{test}', function() {
    return 'Get test route content. Server request method:'. $_SERVER['REQUEST_METHOD'];
})->middleware(\Demo\AuthMiddleware::class);
//
//$router->get(['about', 'contact'], function() {
//    return ['name' => 'Test bangla char', 'age' => 45];
//});
//
//$router->get('/user/{userId}/edit/{another}', function($userId, $another) {
//    return 'get content {userId}: '.$userId.' {another}: '.$another.'. Server request method:'. $_SERVER['REQUEST_METHOD'];
//});
//
//$router->post('test', function() {
//    return 'post content. Server request method:'. $_SERVER['REQUEST_METHOD'];
//});
//
//$router->put('test', function() {
//    return 'put content. Server request method:'. $_SERVER['REQUEST_METHOD'];
//});
//
//$router->patch('test', function() {
//    return 'patch content. Server request method:'. $_SERVER['REQUEST_METHOD'];
//});
//
//$router->delete('test', function() {
//    return 'delete content. Server request method:'. $_SERVER['REQUEST_METHOD'];
//});
//
//$router->options('test', function() {
//    return 'options content. Server request method:'. $_SERVER['REQUEST_METHOD'];
//});
//
//$router->match(['GET', 'POST'], 'getpost', function() {
//    return 'GET POST content. Server request method:'. $_SERVER['REQUEST_METHOD'];
//});
//

$router->get('controller', 'Demo\Controller@index')->name('controller.index');
//$router->any('any', function() {
//    return 'any content. Server request method:'. $_SERVER['REQUEST_METHOD'];
//});

//$router->crud('/crud', function () {
//    return 'crud content. Server request method:'. $_SERVER['REQUEST_METHOD'];
//});

$router->run();

//print_r($router->getUrlByName('controller.index'));

dd($router->getRouteList());