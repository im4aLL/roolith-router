## Roolith router
A simple router class

### Install
```
composer require roolith/router
```

### Basic Usage
```php
use Roolith\Router;

require_once __DIR__ . '/PATH_TO_AUTOLOAD/autoload.php';

$router = new Router();
$router->setBaseUrl('http://localhost/your_project_root/');

$router->get('/', function() {
    return 'Roolith router';
});

$router->run();
```

#### More usage
```php
$router->get('/test', function() {
    return 'Test route';
});

$router->post('/test', function() {
    return 'post content';
});

$router->put('/test', function() {
    return 'put content';
});

$router->patch('/test', function() {
    return 'patch content';
});

$router->delete('/test', function() {
    return 'delete content';
});

$router->options('/test', function() {
    return 'options content';
});
```

#### Route param
```php
$router->get('user/{id}', function($id) {
    return 'User id '.$id;
});

$router->get('/user/{userId}/edit/{another}', function($userId, $another) {
    return 'get content {userId}: '.$userId.' {another}: '.$another;
});
```

#### Multiple method at once
```php
$router->get(['user', 'profile'], function() {
    return ['name' => 'John', 'age' => 45];
});
```

#### Controller method
```php
$router->get('controller', 'Demo\Controller@index');
```

#### Named route
```php
$router->get('controller', 'Demo\Controller@index')->name('controller.index');
```

#### Wildcard route
```php
$router->any('any', function() {
    return 'any content. Server request method:'. $_SERVER['REQUEST_METHOD'];
});
```

#### CRUD route
```php
$router->crud('/crud', function () {
    return 'crud content.';
});
```
Above example is equivalent to
```php
$router->get('/crud', function() {})->name('crud.index');
$router->get('/crud/create', function() {})->name('crud.create');
$router->get('/crud/{item}', function() {})->name('crud.show');
$router->get('/crud/{item}/edit', function() {})->name('crud.edit');
$router->post('/crud', function() {})->name('crud.store');
$router->put('/crud/{item}', function() {})->name('crud.update');
$router->patch('/crud/{item}', function() {})->name('crud.update');
$router->delete('/crud/{item}', function() {})->name('crud.destroy');
```

If there is controller
```php
$router->crud('/crud', 'Controller');
```
Above example is equivalent to
```php
$router->get('/crud',               'Controller@index')->name('crud.index');
$router->get('/crud/create',        'Controller@create')->name('crud.create');
$router->get('/crud/{item}',        'Controller@show')->name('crud.show');
$router->get('/crud/{item}/edit',   'Controller@edit')->name('crud.edit');
$router->post('/crud',              'Controller@store')->name('crud.store');
$router->put('/crud/{item}',        'Controller@update')->name('crud.update');
$router->patch('/crud/{item}',      'Controller@update')->name('crud.update');
$router->delete('/crud/{item}',     'Controller@destroy')->name('crud.destroy');
```

#### Redirect route
```php
$router->redirect('/redirect', '/redirected');
$router->redirect('/redirect', '/redirected', 302);
```

#### Optional param route
```php
$router->get('name/{name?}', function($name = 'Default name') {
    return "Your name is - $name";
});
```

#### Middleware
```php
$router->get('/admin/dashboard', function() {
    return 'Dashboard content';
})->middleware(\Demo\AuthMiddleware::class);
```

#### Group route
```php
$router->group(['middleware' => \Demo\AuthMiddleware::class, 'urlPrefix' => 'user/{userId}', 'namePrefix' => 'user.'], function () use ($router) {
    $router->get('profile', function ($userId){
        return "profile route: User id: $userId";
    })->name('profile');

    $router->get('action/{actionId}', function ($userId, $actionId){
        return "action route: User id: $userId and action id $actionId";
    })->name('action');
});
```

#### Get all route list
```php
$router->getRouteList();
```

#### Get route url by name
```php
$router->getUrlByName('controller.index');
```