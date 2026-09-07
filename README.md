## Roolith router
Lightweight PHP router with route params, middleware, groups, CRUD, redirects, and named URLs.
### Install
```
composer require roolith/router
```
### Setup
```php
use Roolith\Route\Router;
require_once __DIR__ . '/PATH_TO_AUTOLOAD/autoload.php';
$router = new Router();
$router->setBaseUrl('http://localhost/your_project_root/');
```
You can also pass settings to the constructor, which is equivalent to calling the setters below.
```php
$router = new Router([
    'base_url' => 'http://localhost/your_project_root/',
    'view_dir' => __DIR__ . '/views',
    'use_di' => true,
]);
```
`setBaseUrl(string $url)` sets the base used for stripping the request URL and for building redirect and named URLs; `getBaseUrl()` returns it. `setViewDir(string $dir)` sets the directory used by `getViewHtmlByStatusCode()` for `<code>.php` error views. `setUseDI(bool $useDI)` switches controller dispatch between PHP-DI (`true`, default) and plain `new Class()` (`false`). Bare and trailing-slash bases behave identically (`http://test.com` and `http://test.com/` build the same URLs).
### Basic Usage
```php
$router->get('/', function() {
    return 'Roolith router';
});
$router->run();
```
`run()` matches the current method + path, runs middleware, then runs the handler, and returns `$this`. Unknown paths emit 404 `Route doesn't exists`. A path that exists for another method emits 405 `Method Not Allowed. Allowed: ...` with an `Allow` header. Unknown verbs (HEAD/TRACE/custom) never match directly and fall through to the same 405/404 logic. The 405 probe is side-effect free and does not populate route params.
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
All verbs accept a single path or an array of paths, so `post(['a', 'b'], $cb)`, `put(['a', 'b'], $cb)`, and the rest all register one route per path. Paths are stored with a leading `/`, so `'user'` and `'/user'` are the same route. Trailing slashes are literal (`'/a/'` only matches `'/a/'`), so prefer slash-less registration. The string `'0'` is a valid path and registers as `'/0'`. Empty, null, or false paths and callbacks are skipped as no-ops.
#### Route param
```php
$router->get('user/{id}', function($id) {
    return 'User id '.$id;
});
$router->get('/user/{userId}/edit/{another}', function($userId, $another) {
    return 'get content {userId}: '.$userId.' {another}: '.$another;
});
```
Matched values are passed to the handler as positional payload in segment order. Static dots are literal (`/file/about.html` does not match `/file/aboutXhtml`). Params accept dots (`/file/a.txt` matches with payload `['a.txt']`) and unicode slugs (`/post/cafe-2026`, `/post/{slug}` works end to end). Request URL segments are sanitized per RFC 3986 pchar plus unicode letters/numbers, with `?...` query and `#...` fragment stripped per segment.
```php
use Roolith\Route\Request;
use Roolith\Route\Response;
class ShowMiddleware extends \Roolith\Route\Middleware {
    public function process(Request $request, Response $response): bool {
        $id = $request->getParam('id');
        $q = $request->getUrlParam('q');
        return true;
    }
}
```
`$request->getParam('id')` returns a matched `{id}` value or `false` when absent. `$request->getUrlParam('q')` returns the sanitized `$_GET['q']` value (spaces allowed) or `null` when absent. `getRequestMethod()` is uppercased, so a lowercase `get` still matches `GET`.
#### Multiple route at once
```php
$router->get(['user', 'profile'], function() {
    return ['name' => 'John', 'age' => 45];
});
```
Returning an array or object sends JSON (`Content-Type: application/json`); returning a string sends HTML. This applies to closures and controller returns alike.
#### Multiple method at once
```php
$router->match(['GET', 'POST'], '/user', function() {
    return 'GET POST content.';
});
```
`match()` uppercases entries and ignores unknown verbs, so `['GET', 'post', 'BOGUS']` registers only `GET` and `POST`. `match()` also accepts an array path and an optional fourth `$name` argument: `$router->match(['GET', 'POST'], ['/a', '/b'], $cb, 'user')`. `name()` and `middleware()` called after `match()` apply to the whole slice (both `GET` and `POST` routes), not just the last one.
#### Controller method
Supported verbs for string and array callbacks: get/post/put/patch/delete/options/match/any.
```php
$router->get('controller', 'Demo\Controller@index');
// Identical array form (also works with match()/any()):
$router->get('controller', [\Demo\Controller::class, 'index']);
$router->match(['GET', 'POST'], '/user', [\Demo\Controller::class, 'index']);
$router->any('/user', [\Demo\Controller::class, 'index']);
// With route param:
$router->get('user/{id}', [\Demo\Controller::class, 'show']);
```
Note: `[$instance, 'method']` and static `[Class, 'staticMethod']` callables stay as-is and dispatch directly; non-static `[Class, 'method']` normalizes to `Class@method` and uses DI.
With `use_di: true` the controller is resolved via the PHP-DI container (constructor dependencies injected); with `use_di: false` it is created with plain `new Class()`. Route params are passed to the controller method the same way as closures. A missing class emits one 404 (`Class X doesn't exist`); a missing method emits one 404 (`method doesn't exist in X`); a string without `@` emits one 500 (`Invalid controller reference ...`); an invalid array shape (wrong count, non-string parts, empty-string parts) or other residual handler emits one 500 (`Invalid route handler`). `['0', '0']` is allowed and normalizes to `'0@0'`, while `['', '']` is skipped as a no-op. DI or legacy instantiation failures emit one generic 500 without leaking the raw exception; the detail is written to `error_log`.
#### Named route
```php
$router->get('controller', 'Demo\Controller@index')->name('controller.index');
```
`name()` appends to the whole last registration slice, so after `match()`, `any()`, or an array path every registered route gets the name. Inside a group the group `namePrefix` is prepended first, then `name()` appends. `match()` and `any()` also accept the name inline as the last argument.
#### Wildcard route
```php
$router->any('any', function() {
    return 'any content. Server request method:'. $_SERVER['REQUEST_METHOD'];
});
```
`any()` registers one route per known method (GET, POST, PUT, PATCH, DELETE, OPTIONS), accepts a string or array path, and accepts an optional third `$name` argument. `name()` and `middleware()` after `any()` apply to all 6 routes.
#### CRUD route
```php
$router->crud('/crud', function () {
    return 'crud content.';
});
```
Above example registers 10 routes (one per method expansion) and is equivalent to
```php
$router->get('/crud', function() {})->name('crud.index');
$router->get('/crud/create', function() {})->name('crud.create');
$router->get('/crud/{param}', function() {})->name('crud.show');
$router->get('/crud/{param}/edit', function() {})->name('crud.edit');
$router->post('/crud', function() {})->name('crud.store');
$router->post('/crud/{param}', function() {})->name('crud._update');
$router->post('/crud/{param}/delete', function() {})->name('crud._destroy');
$router->put('/crud/{param}', function() {})->name('crud.update');
$router->patch('/crud/{param}', function() {})->name('crud.update');
$router->delete('/crud/{param}', function() {})->name('crud.destroy');
```
If there is controller
```php
$router->crud('/crud', 'Controller');
```
Above example is equivalent to
```php
$router->get('/crud',               'Controller@index')->name('crud.index');
$router->get('/crud/create',        'Controller@create')->name('crud.create');
$router->get('/crud/{param}',       'Controller@show')->name('crud.show');
$router->get('/crud/{param}/edit',  'Controller@edit')->name('crud.edit');
$router->post('/crud',              'Controller@store')->name('crud.store');
$router->post('/crud/{param}',      'Controller@update')->name('crud._update');
$router->post('/crud/{param}/delete','Controller@destroy')->name('crud._destroy');
$router->put('/crud/{param}',       'Controller@update')->name('crud.update');
$router->patch('/crud/{param}',     'Controller@update')->name('crud.update');
$router->delete('/crud/{param}',    'Controller@destroy')->name('crud.destroy');
```
`crud()` also accepts an array base: `[Controller::class]` or `[Controller::class, 'ignored']` expands to `Controller@<crudMethod>` per route (the original array method is ignored). Closures are reused as-is for all crud routes.
#### Redirect route
```php
$router->redirect('/redirect', '/redirected');
$router->redirect('/redirect', '/redirected', 302);
```
`redirect()` defaults to 301 and registers the source for all 6 methods. Relative targets are joined to the base URL with exactly one slash; absolute targets containing `://` are kept as-is (plain `httpfoo` is treated as a relative path). Redirect sources are literal paths: optional placeholders such as `/a/{x?}` are not expanded, so register each concrete source with a separate call. Redirects honor group `urlPrefix`/`namePrefix`, and dispatch is no-exit by design: the `Location` header is sent (CRLF-sanitized, skipped when headers were already sent) with the route status code, then execution continues.
#### Optional param route
```php
$router->get('name/{name?}', function($name = 'Default name') {
    return "Your name is - $name";
});
```
Optional segments expand to prefix routes: `'name/{name?}'` registers `/name` and `/name/{name}`. Multiple optionals expand as a prefix chain (`'a/{b?}/c/{d?}'` registers `/a`, `/a/{b}/c`, `/a/{b}/c/{d}` with no duplicates and no `/a/c` shortcut). A leading optional maps its empty prefix to root (`'{lang?}/about'` registers `/` and `/{lang}/about`). Give the handler a default value for the missing-param case.
#### Middleware
```php
$router->get('/admin/dashboard', function() {
    return 'Dashboard content';
})->middleware(\Demo\AuthMiddleware::class);
```
Middleware must extend `Roolith\Route\Middleware` and implement `process(Request $request, Response $response): bool`. Return `true` to continue or `false` to block; a blocked request emits one response with body `Invalid request` and the middleware `$status_code` (default 403, set `$status_code = 401` for 401, etc.). Chain or stack them with repeated calls or arrays: `->middleware(A::class)->middleware(B::class)` runs `A` then `B`; `->middleware([A::class, $instance])` and already-instantiated entries also work and are resolved via DI with plain-instantiation fallback. An unknown/invalid entry emits one 500 (`Middleware X doesn't exist or is invalid`); a throwing `process()` is logged and emits one generic 500 (`Middleware Error`).
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
Both `function ($router) { ... }` and `function () use ($router) { ... }` styles work because the group callback receives this router instance. `urlPrefix` is joined and normalized (`'user/'` + `'/profile'` becomes `/user/profile`); placeholders in the prefix become leading handler args. `namePrefix` concatenates, `middleware` appends outer-first, and nested groups merge (`outer` + `inner` gives `/outer/inner/p`, `outer.inner.p`, `[Outer, Inner]`) with the outer settings restored afterwards. Route-level `->middleware()` appends after group middleware. Groups also apply to `redirect()` paths/names. `getGroupSettings()` returns the current settings array or `false` when empty; `setGroupSettings()` / `resetGroupSettings()` manage them manually.
#### Active route
```php
$active = $router->activeRoute();
```
`activeRoute()` returns the route matched for the current request method + URL (with `payload` for `{param}` values) or `null`/falsy when nothing matches.
#### Get all route list
```php
$router->getRouteList();
```
Each entry has `method`, `path`, `execute` or `redirect`, `name`, `code` (redirects), `middleware` (when set), and `payload` (when matched with params).
#### Formatted route list
```php
echo $router->formattedRouteList();
```
`formattedRouteList()` renders `getRouteList()` as a terminal-friendly ASCII table with `Method`, `Path`, `Name`, `Action`, and `Middleware` columns (`-` for missing values, `Closure` for closures, `Redirect to <target> (<code>)` for redirects, `No routes registered.` when empty). `Roolith\Route\RouteTableRenderer::render($router->getRouteList())` is the underlying static call.
#### Get route url by name
```php
$router->getUrlByName('controller.index');
$router->getUrlByName('user.show', ['id' => 1]);
```
Placeholders are replaced with `urlencode()`d values, so `['q' => 'a b/c']` becomes `a+b%2Fc`. First match wins on duplicate names. A missing name returns the base URL unchanged (empty base returns `''`). `{key?}` is tolerated as `{key}`, and placeholder keys with regex chars are matched literally.
#### Responses
```php
use Roolith\Route\Response;
$response = new Response();
$response->body('hello');
$response->body(['hello' => 'world']);
$html = $response->renderBody('hello');
$last = $response->getLastOutput();
$response->setStatusCode(404);
$code = $response->getStatusCode();
$response->setHeaderJson();
$response->setHeaderHtml();
$response->setHeaderPlain();
$has = $response->hasHeaderContentType();
$response->errorResponse('Oops', 500);
$response->errorJson(['error' => 'Oops'], 500);
$response->redirect('http://example.com/target');
```
`body()` echoes for BC and also stores/returns the rendered string via `renderBody()`/`getLastOutput()`. Status and `Content-Type` sends are skipped when headers were already sent (CLI/prior output safe). `errorResponse()` is HTML-only and defaults to 500 (was 403); pass 403/404 explicitly where needed. `errorJson()` mirrors the same status contract with a JSON body. `outputJson()` throws `RuntimeException('JSON encoding failed: ...')` on encoding failure. `redirect()` strips CR/LF and does not exit.
#### Error views
```php
$router->setViewDir(__DIR__ . '/views');
$html = $router->getViewHtmlByStatusCode(404, 'fallback message');
```
When `view_dir` is set, `<code>.php` under that dir is included with exactly `$statusCode` and `$message` available (isolated static scope, no `$this`, no `$filePath` leak); otherwise the fallback `$message` is returned as-is. Missing view files also return `$message`.
#### For development
```
./vendor/bin/phpunit --testdox tests --stderr
```
