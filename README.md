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

#### Multiple route at once
```php
$router->get(['user', 'profile'], function() {
    return ['name' => 'John', 'age' => 45];
});
```

#### Multiple method at once
```php
$router->match(['GET', 'POST'], '/user', function() {
    return 'GET POST content.';
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

#### For development
```
./vendor/bin/phpunit --testdox tests --stderr
```

Expected unit test result
```
Request
 ✔ Should get current url
 ✔ Should able to set base url
 ✔ Should get requested method
 ✔ Should get requested url without base url
 ✔ Should remove non allowed character from url string
 ✔ Should able to set and get request param

Response
 ✔ Should have header content type set to false
 ✔ Should able to set status code
 ✔ Should invoke once output html method if content is html
 ✔ Should invoke once output json method if content is array
 ✔ Should set json header
 ✔ Should set html header
 ✔ Should set plain header
 ✔ Should output json
 ✔ Should output html
 ✔ Should have error response
Something went wrong
Router
 ✔ Should initialize router array
 ✔ Should have group settings array
 ✔ Should able to set group settings
 ✔ Should able to reset group settings
 ✔ Should able to add get route
 ✔ Should able to add post route
 ✔ Should able to add put route
 ✔ Should able to add patch route
 ✔ Should able to add delete route
 ✔ Should able to add options route
 ✔ Should able to add multiple method route at once
 ✔ Should able to add wildcard route
 ✔ Should able to add crud route
 ✔ Should have default route for crud
 ✔ Should have create route for crud
 ✔ Should have show route for crud
 ✔ Should have edit route for crud
 ✔ Should have post route for crud
 ✔ Should have update route for crud
 ✔ Should have delete route for crud
 ✔ Should automatic define method name for controller for crud
 ✔ Should able to add redirect route
 ✔ Should able to group routes
 ✔ Should match router by path
 ✔ Should match router by pattern
 ✔ Should match pattern with given url
 ✔ Should add name to route
 ✔ Should get url by name
 ✔ Should add middleware to route
 ✔ Should route run call execute route method once
```