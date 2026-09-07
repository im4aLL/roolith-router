<?php

use PHPUnit\Framework\TestCase;
use Roolith\Route\HttpConstants\HttpMethod;
use Roolith\Route\HttpConstants\HttpResponseCode;
use Roolith\Route\Request;
use Roolith\Route\Response;
use Roolith\Route\Router;
use Roolith\Route\Traits\EncoderTrait;

class Chunk5EncoderHarness
{
    use EncoderTrait;
}

class Chunk5CoverageTest extends TestCase
{
    private ?string $previousRequestMethod = null;

    public function setUp(): void
    {
        parent::setUp();
        $this->previousRequestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
    }

    public function tearDown(): void
    {
        if ($this->previousRequestMethod === null && isset($_SERVER['REQUEST_METHOD'])) {
            unset($_SERVER['REQUEST_METHOD']);
        } elseif ($this->previousRequestMethod !== null) {
            $_SERVER['REQUEST_METHOD'] = $this->previousRequestMethod;
        }

        $this->previousRequestMethod = null;
    }

    private function mockRequest(string $currentUrl, string $method = 'GET'): Request
    {
        $_SERVER['REQUEST_METHOD'] = $method;

        $request = $this->getMockBuilder(Request::class)->onlyMethods(['getCurrentUrl'])->getMock();
        $request->method('getCurrentUrl')->willReturn($currentUrl);

        return $request;
    }

    private function runRouter(Router $router): string
    {
        ob_start();
        $router->run();

        return (string) ob_get_clean();
    }

    public function testDottedParamMatchesFileExtension()
    {
        // Covers 2.1: params with dot must match.
        $router = new Router();
        $router->get('file/{name}', function () {
            return 'done';
        });

        $ref = new ReflectionMethod(Router::class, 'getRequestedRouter');
        $ref->setAccessible(true);
        $matched = $ref->invoke($router, '/file/a.txt', HttpMethod::GET);

        $this->assertIsArray($matched);
        $this->assertSame('/file/{name}', $matched['path']);
        $this->assertSame(['a.txt'], $matched['payload']);
    }

    public function testUnicodeSlugMatchesEndToEnd()
    {
        // Covers 2.1: unicode slugs survive matcher and sanitizer.
        $request = $this->mockRequest('http://test.com/post/café-2026');
        $response = new Response();
        $router = new Router([], $response, $request);
        $router->get('post/{slug}', function ($slug) {
            return 'slug:' . $slug;
        });

        $output = $this->runRouter($router);

        $this->assertSame('slug:café-2026', $output);
    }

    public function testMultiOptionalExpandsWithoutDuplicates()
    {
        // Covers 2.4: multi-optional expansion has no duplicates.
        $router = new Router();
        $router->get('a/{b?}/c/{d?}', function () {
            return 'done';
        });

        $paths = array_column($router->getRouteList(), 'path');

        $this->assertCount(3, $paths);
        $this->assertContains('/a', $paths);
        $this->assertContains('/a/{b}/c', $paths);
        $this->assertContains('/a/{b}/c/{d}', $paths);
        $this->assertSame($paths, array_values(array_unique($paths)));
    }

    public function testLeadingOptionalMapsEmptyPrefixToRoot()
    {
        // Covers 2.14: leading-optional empty prefix maps to root.
        $router = new Router();
        $router->get('{lang?}/about', function () {
            return 'done';
        });

        $paths = array_column($router->getRouteList(), 'path');

        $this->assertSame(['/', '/{lang}/about'], $paths);
    }

    public function testMatchNormalizesCaseAndIgnoresUnknown()
    {
        // Covers 2.5: match() uppercases entries and drops unknown verbs.
        $router = new Router();
        $router->match(['GET', 'post', 'BOGUS'], '/user', function () {
            return 'done';
        });

        $routes = $router->getRouteList();

        $this->assertCount(2, $routes);
        $this->assertSame(HttpMethod::GET, $routes[0]['method']);
        $this->assertSame(HttpMethod::POST, $routes[1]['method']);
    }

    public function testLowercaseRequestMethodNormalizesToUpper()
    {
        // Covers 2.12: lowercase request method normalizes at storage.
        $previous = $_SERVER['REQUEST_METHOD'] ?? null;
        $_SERVER['REQUEST_METHOD'] = 'post';

        try {
            $request = new Request();

            $this->assertSame(HttpMethod::POST, $request->getRequestMethod());
        } finally {
            if ($previous === null) {
                unset($_SERVER['REQUEST_METHOD']);
            } else {
                $_SERVER['REQUEST_METHOD'] = $previous;
            }
        }
    }

    public function testNameSliceAfterMatchAndAny()
    {
        // Covers 2.6: name() applies to whole match()/any() slice.
        $router = new Router();
        $router->match(['GET', 'POST'], '/user', function () {
            return 'done';
        })->name('user');

        $routes = $router->getRouteList();

        $this->assertCount(2, $routes);
        $this->assertSame('user', $routes[0]['name']);
        $this->assertSame('user', $routes[1]['name']);

        $anyRouter = new Router();
        $anyRouter->any('/page', function () {
            return 'done';
        })->name('page');

        $anyRoutes = $anyRouter->getRouteList();

        $this->assertCount(6, $anyRoutes);

        foreach ($anyRoutes as $route) {
            $this->assertSame('page', $route['name']);
        }
    }

    public function testMiddlewareSliceAfterMatchAndAny()
    {
        // Covers 2.7: middleware() applies to whole match()/any() slice.
        $router = new Router();
        $router->match(['GET', 'POST'], '/user', function () {
            return 'done';
        })->middleware('DemoMiddleware');

        $routes = $router->getRouteList();

        $this->assertCount(2, $routes);
        $this->assertSame(['DemoMiddleware'], $routes[0]['middleware']);
        $this->assertSame(['DemoMiddleware'], $routes[1]['middleware']);

        $anyRouter = new Router();
        $anyRouter->any('/page', function () {
            return 'done';
        })->middleware('DemoMiddleware');

        $anyRoutes = $anyRouter->getRouteList();

        $this->assertCount(6, $anyRoutes);

        foreach ($anyRoutes as $route) {
            $this->assertSame(['DemoMiddleware'], $route['middleware']);
        }
    }

    public function testNestedGroupMergesPrefixesAndMiddleware()
    {
        // Covers 2.8 + 2.13: nested groups merge url/name prefixes and append middleware outer-first.
        $router = new Router();
        $router->group(['urlPrefix' => 'outer', 'namePrefix' => 'outer.', 'middleware' => 'OuterMiddleware'], function ($router) {
            $router->group(['urlPrefix' => 'inner', 'namePrefix' => 'inner.', 'middleware' => 'InnerMiddleware'], function ($router) {
                $router->get('p', function () {
                    return 'done';
                })->name('p');
            });
        });

        $routes = $router->getRouteList();

        $this->assertCount(1, $routes);
        $this->assertSame('/outer/inner/p', $routes[0]['path']);
        $this->assertSame('outer.inner.p', $routes[0]['name']);
        $this->assertSame(['OuterMiddleware', 'InnerMiddleware'], $routes[0]['middleware']);
    }

    public function testRedirectRegistersAllMethodsAndHonorsGroups()
    {
        // Covers 2.10: redirect() registers all 6 methods and honors group settings.
        $router = new Router();
        $router->redirect('/redirect', '/redirected');

        $routes = $router->getRouteList();

        $this->assertCount(6, $routes);
        $this->assertSame(HttpMethod::all(), array_column($routes, 'method'));

        $grouped = new Router();
        $grouped->group(['urlPrefix' => 'admin', 'namePrefix' => 'admin.'], function ($router) {
            $router->redirect('/old', '/new');
        });

        $groupedRoutes = $grouped->getRouteList();

        $this->assertCount(6, $groupedRoutes);

        foreach ($groupedRoutes as $route) {
            $this->assertSame('/admin/old', $route['path']);
            $this->assertSame('admin.', $route['name']);
        }
    }

    public function testJoinContractBareVsTrailingSlashBase()
    {
        // Covers 2.11: bare vs trailing-slash bases produce identical URLs.
        $first = new Router(['base_url' => 'http://test.com']);
        $first->redirect('/a', 'target');

        $second = new Router(['base_url' => 'http://test.com/']);
        $second->redirect('/a', 'target');

        $firstRoutes = $first->getRouteList();
        $secondRoutes = $second->getRouteList();

        $this->assertSame($firstRoutes[0]['redirect'], $secondRoutes[0]['redirect']);
        $this->assertSame('http://test.com/target', $firstRoutes[0]['redirect']);
    }

    public function testGetUrlByNameSpecialCharKeysAndNotFoundPin()
    {
        // Covers 3.10: preg_quote on keys, urlencode values, not-found returns base unchanged.
        $router = new Router(['base_url' => 'http://test.com']);
        $router->get('/a/{b.c}/d', function () {
            return 'done';
        })->name('dotted');
        $router->get('search/{q}', function () {
            return 'done';
        })->name('search');

        $bareRouter = new Router();
        $bareRouter->get('/a/{b.c}/d', function () {
            return 'done';
        })->name('dotted');

        $this->assertSame('/a/1/d', $bareRouter->getUrlByName('dotted', ['b.c' => '1']));
        $this->assertSame('http://test.com', $router->getUrlByName('missing'));
        $this->assertSame('http://test.com/search/a+b%2Fc', $router->getUrlByName('search', ['q' => 'a b/c']));
    }

    public function testErrorJsonShapeWithDefaultAndExplicitStatus()
    {
        // Covers 3.7: errorJson() mirrors status contract with JSON body.
        $response = new Response();

        ob_start();
        $response->errorJson(['error' => 'oops']);
        $echoed = ob_get_clean();

        $this->assertSame(HttpResponseCode::INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertSame(['error' => 'oops'], json_decode($echoed, true));

        $second = new Response();

        ob_start();
        $second->errorJson(['error' => 'missing'], HttpResponseCode::NOT_FOUND);
        $secondEchoed = ob_get_clean();

        $this->assertSame(HttpResponseCode::NOT_FOUND, $second->getStatusCode());
        $this->assertSame(['error' => 'missing'], json_decode($secondEchoed, true));
    }

    public function testMethodNotAllowedVsNotFound()
    {
        // Covers 4.10: path exists for another method gives 405 with Allow, unknown path gives 404.
        $request405 = $this->mockRequest('http://test.com/only-get', 'POST');
        $response405 = new Response();
        $router405 = new Router([], $response405, $request405);
        $router405->get('/only-get', function () {
            return 'done';
        });

        $output405 = $this->runRouter($router405);

        $this->assertSame(HttpResponseCode::METHOD_NOT_ALLOWED, $response405->getStatusCode());
        $this->assertSame('Method Not Allowed. Allowed: GET', $output405);

        $request404 = $this->mockRequest('http://test.com/unknown');
        $response404 = new Response();
        $router404 = new Router([], $response404, $request404);
        $router404->get('/exists', function () {
            return 'done';
        });

        $output404 = $this->runRouter($router404);

        $this->assertSame(HttpResponseCode::NOT_FOUND, $response404->getStatusCode());
        $this->assertSame("Route doesn't exists", $output404);
    }

    public function testSingleResponseOnDiFailure()
    {
        // Covers 4.4: DI failure emits exactly one 500 response.
        $request = $this->mockRequest('http://test.com/di');
        $response = new Response();
        $router = new Router([], $response, $request);
        $router->get('/di', 'DispatchNeedsScalar@hello');

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertSame('Dependency Injection Error On DispatchNeedsScalar hello', $output);
    }

    public function testNonAtControllerStringEmitsSingle500()
    {
        // Covers 4.5: non-@ controller string emits single 500.
        $request = $this->mockRequest('http://test.com/bad');
        $response = new Response();
        $router = new Router([], $response, $request);
        $router->get('/bad', 'NoAtControllerReference');

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertSame("Invalid controller reference 'NoAtControllerReference' (expected 'Class@method')", $output);
    }

    public function testMissingClassEmitsSingle404()
    {
        // Covers 4.5: missing controller class emits single 404.
        $request = $this->mockRequest('http://test.com/gone');
        $response = new Response();
        $router = new Router([], $response, $request);
        $router->get('/gone', 'Missing_Chunk5_Controller_XYZ@run');

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::NOT_FOUND, $response->getStatusCode());
        $this->assertSame("Class Missing_Chunk5_Controller_XYZ doesn't exist", $output);
    }

    public function testEncoderMultibyteData()
    {
        // Covers 4.8: EncoderTrait converts latin1 bytes via mb_convert_encoding.
        $harness = new Chunk5EncoderHarness();

        $this->assertSame(["\xc3\xa9"], array_values($harness->anythingToUtf8(["k" => "\xe9"])));

        $deep = new stdClass();
        $deep->name = "\xe9";
        $this->assertSame("\xc3\xa9", $harness->anythingToUtf8($deep)->name);
    }

    public function testConflictCodeIs409()
    {
        // Covers 1.5 follow-up: CONFLICT must be 409, distinct from REQUEST_TIMEOUT 408.
        $this->assertSame(409, HttpResponseCode::CONFLICT);
        $this->assertSame(408, HttpResponseCode::REQUEST_TIMEOUT);
        $this->assertNotSame(HttpResponseCode::REQUEST_TIMEOUT, HttpResponseCode::CONFLICT);
    }

    public function testErrorResponseDefaultsTo500And404IsExplicit()
    {
        // Covers 1.4 follow-up: default is 500 (BC break from 403); 404s are explicit at call-sites.
        $response = new Response();

        ob_start();
        $response->errorResponse();
        ob_get_clean();

        $this->assertSame(HttpResponseCode::INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertNotSame(HttpResponseCode::FORBIDDEN, $response->getStatusCode());

        $request = $this->mockRequest('http://test.com/nope');
        $dispatchResponse = new Response();
        $router = new Router([], $dispatchResponse, $request);
        $router->get('/exists', function () {
            return 'done';
        });

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::NOT_FOUND, $dispatchResponse->getStatusCode());
        $this->assertSame("Route doesn't exists", $output);
    }
}
