<?php

use PHPUnit\Framework\TestCase;
use Roolith\Route\HttpConstants\HttpResponseCode;
use Roolith\Route\Middleware;
use Roolith\Route\Request;
use Roolith\Route\Response;
use Roolith\Route\Router;
use Roolith\Route\Traits\EncoderTrait;

class DispatchAllowMiddleware extends Middleware
{
    public function process(Request $request, Response $response): bool
    {
        return true;
    }
}

class DispatchBlockMiddleware extends Middleware
{
    public function process(Request $request, Response $response): bool
    {
        return false;
    }
}

class DispatchThrowMiddleware extends Middleware
{
    public function process(Request $request, Response $response): bool
    {
        throw new \RuntimeException("boom\r\ninjected");
    }
}

class DispatchUnauthorizedMiddleware extends Middleware
{
    public int $status_code = HttpResponseCode::UNAUTHORIZED;

    public function process(Request $request, Response $response): bool
    {
        return false;
    }
}

class DispatchSimpleController
{
    public function hello(): string
    {
        return 'hello';
    }
}

class DispatchNeedsScalar
{
    public function __construct(string $apiKey)
    {
    }

    public function hello(): string
    {
        return 'hello';
    }
}

class DispatchNeedsDependency
{
    public function __construct(DispatchSimpleController $controller)
    {
    }

    public function hello(): string
    {
        return 'hello';
    }
}

class DispatchParamController
{
    public function show(string $id): string
    {
        return 'show '.$id;
    }
}

class DispatchStaticController
{
    public static function hello(): string
    {
        return 'static hello';
    }
}

class DispatchCrudArrayController
{
    public function index(): string
    {
        return 'crud index';
    }

    public function create(): string
    {
        return 'crud create';
    }

    public function show(string $id): string
    {
        return 'crud show '.$id;
    }

    public function edit(string $id): string
    {
        return 'crud edit '.$id;
    }

    public function store(): string
    {
        return 'crud store';
    }

    public function update(string $id): string
    {
        return 'crud update '.$id;
    }

    public function destroy(string $id): string
    {
        return 'crud destroy '.$id;
    }
}

class DispatchEncoderHarness
{
    use EncoderTrait;
}

class DispatchTest extends TestCase
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

    private function routerFor(string $currentUrl, string $method = 'GET', array $settings = []): array
    {
        $response = new Response();
        $router = new Router($settings, $response, $this->mockRequest($currentUrl, $method));

        return [$router, $response];
    }

    public function testMissingClassEmitsSingle404()
    {
        [$router, $response] = $this->routerFor('http://test.com/gone');
        $router->get('/gone', 'Missing_Dispatch_Controller_XYZ@run');

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::NOT_FOUND, $response->getStatusCode());
        $this->assertSame("Class Missing_Dispatch_Controller_XYZ doesn't exist", $output);
    }

    public function testMissingAtEmitsSingle500()
    {
        [$router, $response] = $this->routerFor('http://test.com/bad');
        $router->get('/bad', 'NoAtControllerReference');

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertSame("Invalid controller reference 'NoAtControllerReference' (expected 'Class@method')", $output);
    }

    public function testMissingMethodEmitsSingle404()
    {
        [$router, $response] = $this->routerFor('http://test.com/method');
        $router->get('/method', 'DispatchSimpleController@nope');

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::NOT_FOUND, $response->getStatusCode());
        $this->assertSame("nope method doesn't exist in DispatchSimpleController", $output);
    }

    public function testDiFailureEmitsSingle500WithoutRawException()
    {
        [$router, $response] = $this->routerFor('http://test.com/di');
        $router->get('/di', 'DispatchNeedsScalar@hello');

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertSame('Dependency Injection Error On DispatchNeedsScalar hello', $output);
        $this->assertStringNotContainsString('cannot be resolved', $output);
    }

    public function testLegacyDispatchFailureEmitsSingle500()
    {
        [$router, $response] = $this->routerFor('http://test.com/legacy', 'GET', ['use_di' => false]);
        $router->get('/legacy', 'DispatchNeedsDependency@hello');

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertSame('Controller Error On DispatchNeedsDependency hello', $output);
    }

    public function testBlockedMiddlewareEmitsSingleResponseWithMiddlewareStatus()
    {
        [$router, $response] = $this->routerFor('http://test.com/guarded');
        $router->get('/guarded', function () {
            return 'should never render';
        })->middleware('DispatchBlockMiddleware');

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::FORBIDDEN, $response->getStatusCode());
        $this->assertSame('Invalid request', $output);
    }

    public function testAllowedMiddlewareLetsRequestThrough()
    {
        [$router, $response] = $this->routerFor('http://test.com/open');
        $router->get('/open', function () {
            return 'open body';
        })->middleware('DispatchAllowMiddleware');

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::OK, $response->getStatusCode());
        $this->assertSame('open body', $output);
    }

    public function testMissingMiddlewareClassEmitsSingle500()
    {
        [$router, $response] = $this->routerFor('http://test.com/mw');
        $router->get('/mw', function () {
            return 'should never render';
        })->middleware('Missing_Dispatch_Middleware_XYZ');

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertSame("Middleware Missing_Dispatch_Middleware_XYZ doesn't exist or is invalid", $output);
    }

    public function testMethodNotAllowedWithAllowMethods()
    {
        [$router, $response] = $this->routerFor('http://test.com/only-get', 'POST');
        $router->get('/only-get', function () {
            return 'done';
        });

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::METHOD_NOT_ALLOWED, $response->getStatusCode());
        $this->assertSame('Method Not Allowed. Allowed: GET', $output);
    }

    public function testUnknownVerbFallsBackTo405WhenPathExists()
    {
        [$router, $response] = $this->routerFor('http://test.com/only-get', 'HEAD');
        $router->get('/only-get', function () {
            return 'done';
        });

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::METHOD_NOT_ALLOWED, $response->getStatusCode());
        $this->assertSame('Method Not Allowed. Allowed: GET', $output);
    }

    public function testUnknownPathEmitsSingle404()
    {
        [$router, $response] = $this->routerFor('http://test.com/unknown');
        $router->get('/exists', function () {
            return 'done';
        });

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::NOT_FOUND, $response->getStatusCode());
        $this->assertSame("Route doesn't exists", $output);
    }

    public function testThrowingMiddlewareEmitsSingle500WithGenericBody()
    {
        [$router, $response] = $this->routerFor('http://test.com/throwing');
        $router->get('/throwing', function () {
            return 'should never render';
        })->middleware('DispatchThrowMiddleware');

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertSame('Middleware Error', $output);
    }

    public function testCustomStatusCodeMiddlewareAlignsBodyAndStatus()
    {
        [$router, $response] = $this->routerFor('http://test.com/custom');
        $router->get('/custom', function () {
            return 'should never render';
        })->middleware('DispatchUnauthorizedMiddleware');

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::UNAUTHORIZED, $response->getStatusCode());
        $this->assertSame('Invalid request', $output);
    }

    public function testMethodNotAllowedLeavesRequestParamsUnchanged()
    {
        $request = $this->mockRequest('http://test.com/user/123', 'POST');
        $response = new Response();
        $router = new Router([], $response, $request);
        $router->get('/user/{id}', function () {
            return 'done';
        });

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::METHOD_NOT_ALLOWED, $response->getStatusCode());
        $this->assertSame('Method Not Allowed. Allowed: GET', $output);
        $this->assertFalse($request->getParam('id'));
    }

    public function testRedirectReturnsThisWithoutExit()
    {
        [$router, $response] = $this->routerFor('http://test.com/old');
        $router->redirect('/old', '/new');

        ob_start();
        $result = $router->run();
        $output = (string) ob_get_clean();

        $continued = true;

        $this->assertSame($router, $result);
        $this->assertTrue($continued);
        $this->assertSame(HttpResponseCode::MOVED_PERMANENTLY, $response->getStatusCode());
        $this->assertSame('', $output);
    }

    public function testGroupedRedirectDispatchesOnPost()
    {
        [$router, $response] = $this->routerFor('http://test.com/admin/old', 'POST');
        $router->group(['urlPrefix' => 'admin'], function ($router) {
            $router->redirect('/old', 'target');
        });

        ob_start();
        $result = $router->run();
        $output = (string) ob_get_clean();

        $continued = true;

        $this->assertSame($router, $result);
        $this->assertTrue($continued);
        $this->assertSame(HttpResponseCode::MOVED_PERMANENTLY, $response->getStatusCode());
        $this->assertSame('', $output);
    }

    public function testViewIsolationExposesOnlyStatusCodeAndMessage()
    {
        $tmpDir = sys_get_temp_dir() . '/dispatch-view-' . uniqid();

        mkdir($tmpDir);
        file_put_contents($tmpDir . '/404.php', '<?= $statusCode ?>:<?= $message ?>:<?= isset($this) ? \'this-leak\' : \'no-this\' ?>:<?= isset($filePath) ? \'filepath-leak\' : \'no-filepath\' ?>');

        try {
            [$router] = $this->routerFor('http://test.com/x', 'GET', ['view_dir' => $tmpDir]);

            $html = $router->getViewHtmlByStatusCode(404, 'hello');

            $this->assertStringContainsString('404:hello', $html);
            $this->assertStringContainsString('no-this', $html);
            $this->assertStringContainsString('no-filepath', $html);
            $this->assertStringNotContainsString('leak', $html);
        } finally {
            @unlink($tmpDir . '/404.php');
            @rmdir($tmpDir);
        }
    }

    public function testEncoderConvertsLatin1ArrayValue()
    {
        $harness = new DispatchEncoderHarness();

        $this->assertSame(["\xc3\xa9"], array_values($harness->anythingToUtf8(["k" => "\xe9"])));
    }

    public function testEncoderShallowBranchConvertsScalarAndLeavesNestedArray()
    {
        $harness = new DispatchEncoderHarness();

        $result = $harness->anythingToUtf8(['k' => "\xe9", 'nested' => ["\xe9"]], false);

        $this->assertSame("\xc3\xa9", $result['k']);
        $this->assertSame(["\xe9"], $result['nested']);
    }

    public function testEncoderConvertsObjectPublicProp()
    {
        $harness = new DispatchEncoderHarness();

        $deep = new stdClass();
        $deep->name = "\xe9";
        $this->assertSame("\xc3\xa9", $harness->anythingToUtf8($deep)->name);

        $shallow = new stdClass();
        $shallow->name = "\xe9";
        $this->assertSame("\xc3\xa9", $harness->anythingToUtf8($shallow, false)->name);
    }

    public function testEncoderLeavesValidUtf8AndScalarsAlone()
    {
        $harness = new DispatchEncoderHarness();

        $this->assertSame('<p>Vakuutan olevani v\xc3\xa4hint\xc3\xa4\xc3\xa4n 18-vuotias</p>', $harness->anythingToUtf8('<p>Vakuutan olevani v\xc3\xa4hint\xc3\xa4\xc3\xa4n 18-vuotias</p>'));
        $this->assertSame(5, $harness->anythingToUtf8(5));
        $this->assertNull($harness->anythingToUtf8(null));
        $this->assertTrue($harness->anythingToUtf8(true));
    }

    public function testArrayCallbackDispatchesSameAsString()
    {
        [$stringRouter, $stringResponse] = $this->routerFor('http://test.com/hello');
        $stringRouter->get('/hello', 'DispatchSimpleController@hello');
        $stringOutput = $this->runRouter($stringRouter);

        [$arrayRouter, $arrayResponse] = $this->routerFor('http://test.com/hello');
        $arrayRouter->get('/hello', ['DispatchSimpleController', 'hello']);
        $arrayOutput = $this->runRouter($arrayRouter);

        $this->assertSame($stringOutput, $arrayOutput);
        $this->assertSame('hello', $arrayOutput);
        $this->assertSame($stringResponse->getStatusCode(), $arrayResponse->getStatusCode());
    }

    public function testArrayCallbackPassesParamPayload()
    {
        [$router, $response] = $this->routerFor('http://test.com/user/42');
        $router->get('/user/{id}', ['DispatchParamController', 'show']);

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::OK, $response->getStatusCode());
        $this->assertSame('show 42', $output);
    }

    public function testArrayCallbackWorksWithLegacyDi()
    {
        [$router, $response] = $this->routerFor('http://test.com/hello', 'GET', ['use_di' => false]);
        $router->get('/hello', ['DispatchSimpleController', 'hello']);

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::OK, $response->getStatusCode());
        $this->assertSame('hello', $output);
    }

    public function testArrayCallbackMissingClassEmitsSingle404()
    {
        [$router, $response] = $this->routerFor('http://test.com/gone');
        $router->get('/gone', ['Missing_Dispatch_Controller_XYZ', 'run']);

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::NOT_FOUND, $response->getStatusCode());
        $this->assertSame("Class Missing_Dispatch_Controller_XYZ doesn't exist", $output);
    }

    public function testArrayCallbackMissingMethodEmitsSingle404()
    {
        [$router, $response] = $this->routerFor('http://test.com/method');
        $router->get('/method', ['DispatchSimpleController', 'nope']);

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::NOT_FOUND, $response->getStatusCode());
        $this->assertSame("nope method doesn't exist in DispatchSimpleController", $output);
    }

    public function testInvalidArrayCallbackEmitsSingle500()
    {
        [$router, $response] = $this->routerFor('http://test.com/bad');
        $router->get('/bad', ['OnlyOne']);

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertSame('Invalid route handler', $output);
    }

    public function testMatchWithArrayCallbackDispatches()
    {
        [$router, $response] = $this->routerFor('http://test.com/hello', 'POST');
        $router->match(['GET', 'POST'], '/hello', ['DispatchSimpleController', 'hello']);

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::OK, $response->getStatusCode());
        $this->assertSame('hello', $output);
    }

    public function testAnyWithArrayCallbackDispatches()
    {
        [$router, $response] = $this->routerFor('http://test.com/hello', 'DELETE');
        $router->any('/hello', ['DispatchSimpleController', 'hello']);

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::OK, $response->getStatusCode());
        $this->assertSame('hello', $output);
    }

    public function testCrudWithSingleElementArrayDispatches()
    {
        [$router, $response] = $this->routerFor('http://test.com/items');
        $router->crud('/items', ['DispatchCrudArrayController']);

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::OK, $response->getStatusCode());
        $this->assertSame('crud index', $output);
    }

    public function testCrudWithIgnoredMethodDispatchesCrudMethod()
    {
        [$router, $response] = $this->routerFor('http://test.com/items/42');
        $router->crud('/items', ['DispatchCrudArrayController', 'ignored']);

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::OK, $response->getStatusCode());
        $this->assertSame('crud show 42', $output);
    }

    public function testInstanceCallableStaysArrayAndDispatches()
    {
        $controller = new DispatchSimpleController();

        [$router, $response] = $this->routerFor('http://test.com/hello');
        $router->get('/hello', [$controller, 'hello']);

        $routes = $router->getRouteList();

        $this->assertSame([$controller, 'hello'], $routes[0]['execute']);

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::OK, $response->getStatusCode());
        $this->assertSame('hello', $output);
    }

    public function testStaticCallableStaysArrayAndDispatches()
    {
        [$router, $response] = $this->routerFor('http://test.com/static');
        $router->get('/static', ['DispatchStaticController', 'hello']);

        $routes = $router->getRouteList();

        $this->assertSame(['DispatchStaticController', 'hello'], $routes[0]['execute']);

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::OK, $response->getStatusCode());
        $this->assertSame('static hello', $output);
    }

    public function testInvalidArrayShapesEmitSingle500()
    {
        $callbacks = [
            ['A', 'B', 'C'],
            ['a' => 1, 'b' => 2],
            [123, null],
        ];

        foreach ($callbacks as $callback) {
            [$router, $response] = $this->routerFor('http://test.com/bad');
            $router->get('/bad', $callback);

            $output = $this->runRouter($router);

            $this->assertSame(HttpResponseCode::INTERNAL_SERVER_ERROR, $response->getStatusCode());
            $this->assertSame('Invalid route handler', $output);
        }
    }
}
