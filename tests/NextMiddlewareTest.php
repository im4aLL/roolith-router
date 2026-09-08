<?php

use PHPUnit\Framework\TestCase;
use Roolith\Route\HttpConstants\HttpResponseCode;
use Roolith\Route\Interfaces\NextMiddlewareInterface;
use Roolith\Route\Middleware;
use Roolith\Route\NextMiddleware;
use Roolith\Route\Request;
use Roolith\Route\Response;
use Roolith\Route\Router;

class NextTrace
{
    public static array $log = [];

    public static function reset(): void
    {
        self::$log = [];
    }
}

class NextLegacyAllow extends Middleware
{
    public function process(Request $request, Response $response): bool
    {
        return true;
    }
}

class NextLegacyBlock extends Middleware
{
    public function process(Request $request, Response $response): bool
    {
        return false;
    }
}

class NextLegacyUnauthorized extends Middleware
{
    public int $status_code = HttpResponseCode::UNAUTHORIZED;

    public function process(Request $request, Response $response): bool
    {
        return false;
    }
}

class NextPassMiddleware implements NextMiddlewareInterface
{
    public function process(Request $request, callable $next): mixed
    {
        return $next($request);
    }
}

class NextAliasPassMiddleware implements NextMiddleware
{
    public function process(Request $request, callable $next): mixed
    {
        return $next($request);
    }
}

class NextBlockFalseMiddleware implements NextMiddlewareInterface
{
    public function process(Request $request, callable $next): mixed
    {
        return false;
    }
}

class NextBlockUnauthorizedMiddleware implements NextMiddlewareInterface
{
    public int $status_code = HttpResponseCode::UNAUTHORIZED;

    public function process(Request $request, callable $next): mixed
    {
        return false;
    }
}

class NextRedirectMiddleware implements NextMiddlewareInterface
{
    public function process(Request $request, callable $next): mixed
    {
        $redirect = new Response();
        $redirect->setStatusCode(HttpResponseCode::MOVED_TEMPORARILY);
        $redirect->redirect('/login');

        return $redirect;
    }
}

class NextForbiddenMiddleware implements NextMiddlewareInterface
{
    public function process(Request $request, callable $next): mixed
    {
        $forbidden = new Response();
        $forbidden->setStatusCode(HttpResponseCode::FORBIDDEN);
        $forbidden->renderBody('blocked csrf');

        return $forbidden;
    }
}

class NextJsonMiddleware implements NextMiddlewareInterface
{
    public function process(Request $request, callable $next): mixed
    {
        return ['ok' => true];
    }
}

class NextStringHijackMiddleware implements NextMiddlewareInterface
{
    public function process(Request $request, callable $next): mixed
    {
        return 'hijacked';
    }
}

class NextTrueMiddleware implements NextMiddlewareInterface
{
    public function process(Request $request, callable $next): mixed
    {
        return true;
    }
}

class NextNullMiddleware implements NextMiddlewareInterface
{
    public function process(Request $request, callable $next): mixed
    {
        return null;
    }
}

class NextThrowMiddleware implements NextMiddlewareInterface
{
    public function process(Request $request, callable $next): mixed
    {
        throw new RuntimeException('next boom');
    }
}

class NextOrderOuterMiddleware implements NextMiddlewareInterface
{
    public function process(Request $request, callable $next): mixed
    {
        NextTrace::$log[] = 'M1-pre';
        $result = $next($request);
        NextTrace::$log[] = 'M1-post';

        return $result;
    }
}

class NextOrderInnerMiddleware implements NextMiddlewareInterface
{
    public function process(Request $request, callable $next): mixed
    {
        NextTrace::$log[] = 'M2-pre';
        $result = $next($request);
        NextTrace::$log[] = 'M2-post';

        return $result;
    }
}

class NextHeaderPostMiddleware implements NextMiddlewareInterface
{
    public function process(Request $request, callable $next): mixed
    {
        $result = $next($request);

        if ($result instanceof Response) {
            $result->setHeader('X-Auth-Checked', '1');

            return $result;
        }

        $wrapped = new Response();
        $wrapped->setStatusCode(HttpResponseCode::OK);
        $wrapped->setHeader('X-Auth-Checked', '1');

        if (is_array($result) || is_object($result)) {
            $wrapped->renderBody($result);
        } else {
            $wrapped->renderBody((string) $result);
        }

        return $wrapped;
    }
}

class NextWithDependencyMiddleware implements NextMiddlewareInterface
{
    public function __construct(private string $marker = 'dep')
    {
    }

    public function process(Request $request, callable $next): mixed
    {
        return $next($request);
    }

    public function marker(): string
    {
        return $this->marker;
    }
}

class NextStatusWinsMiddleware implements NextMiddlewareInterface
{
    public int $status_code = HttpResponseCode::UNAUTHORIZED;

    public function process(Request $request, callable $next): mixed
    {
        $response = new Response();
        $response->setStatusCode(HttpResponseCode::FORBIDDEN);
        $response->renderBody('forbidden body');

        return $response;
    }
}

class NextLegacyThrowMiddleware extends Middleware
{
    public function process(Request $request, Response $response): bool
    {
        throw new RuntimeException('legacy boom');
    }
}

class NextThrowingController
{
    public function boom(): string
    {
        throw new RuntimeException('handler boom');
    }
}

class NextMiddlewareTest extends TestCase
{
    private ?string $previousRequestMethod = null;

    public function setUp(): void
    {
        parent::setUp();
        $this->previousRequestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        NextTrace::reset();
    }

    public function tearDown(): void
    {
        if ($this->previousRequestMethod === null && isset($_SERVER['REQUEST_METHOD'])) {
            unset($_SERVER['REQUEST_METHOD']);
        } elseif ($this->previousRequestMethod !== null) {
            $_SERVER['REQUEST_METHOD'] = $this->previousRequestMethod;
        }

        $this->previousRequestMethod = null;
        NextTrace::reset();
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

    public function testSimpleUsageWorksForLegacyStyle()
    {
        [$router, $response] = $this->routerFor('http://test.com/x');
        $router->get('/x', fn () => 'hi')->middleware(NextLegacyAllow::class);

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::OK, $response->getStatusCode());
        $this->assertSame('hi', $output);
    }

    public function testSimpleUsageWorksForNextStyleClassString()
    {
        [$router, $response] = $this->routerFor('http://test.com/x');
        $router->get('/x', fn () => 'hi')->middleware(NextPassMiddleware::class);

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::OK, $response->getStatusCode());
        $this->assertSame('hi', $output);
    }

    public function testSimpleUsageWorksForNextStyleAlias()
    {
        [$router, $response] = $this->routerFor('http://test.com/x');
        $router->get('/x', fn () => 'hi')->middleware(NextAliasPassMiddleware::class);

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::OK, $response->getStatusCode());
        $this->assertSame('hi', $output);
    }

    public function testNextStyleInstanceForm()
    {
        [$router, $response] = $this->routerFor('http://test.com/x');
        $router->get('/x', fn () => 'hi')->middleware(new NextPassMiddleware());

        $output = $this->runRouter($router);

        $this->assertSame('hi', $output);
        $this->assertSame(HttpResponseCode::OK, $response->getStatusCode());
    }

    public function testNextStyleWithConstructorDependency()
    {
        [$router, $response] = $this->routerFor('http://test.com/x');
        $router->get('/x', fn () => 'hi')->middleware(new NextWithDependencyMiddleware('custom'));

        $output = $this->runRouter($router);

        $this->assertSame('hi', $output);
    }

    public function testOnionOrder()
    {
        [$router, $response] = $this->routerFor('http://test.com/onion');
        $router->get('/onion', function () {
            NextTrace::$log[] = 'handler';

            return 'done';
        })->middleware(NextOrderOuterMiddleware::class)->middleware(NextOrderInnerMiddleware::class);

        $output = $this->runRouter($router);

        $this->assertSame('done', $output);
        $this->assertSame(['M1-pre', 'M2-pre', 'handler', 'M2-post', 'M1-post'], NextTrace::$log);
    }

    public function testGroupOrderOuterFirst()
    {
        [$router, $response] = $this->routerFor('http://test.com/g/inner');
        $router->group(['middleware' => NextOrderOuterMiddleware::class], function ($router) {
            $router->group(['middleware' => NextOrderInnerMiddleware::class], function ($router) {
                $router->get('/g/inner', function () {
                    NextTrace::$log[] = 'handler';

                    return 'ok';
                });
            });
        });

        $output = $this->runRouter($router);

        $this->assertSame('ok', $output);
        $this->assertSame(['M1-pre', 'M2-pre', 'handler', 'M2-post', 'M1-post'], NextTrace::$log);
    }

    public function testMixedGroupStyles()
    {
        [$router, $response] = $this->routerFor('http://test.com/mixed');
        $router->group(['middleware' => NextLegacyAllow::class], function ($router) {
            $router->get('/mixed', fn () => 'mixed ok')->middleware(NextPassMiddleware::class);
        });

        $output = $this->runRouter($router);

        $this->assertSame('mixed ok', $output);
        $this->assertSame(HttpResponseCode::OK, $response->getStatusCode());
    }

    public function testBlockingWithoutNextNeverRunsController()
    {
        [$router, $response] = $this->routerFor('http://test.com/blocked');
        $ran = false;
        $router->get('/blocked', function () use (&$ran) {
            $ran = true;

            return 'should never render';
        })->middleware(NextBlockFalseMiddleware::class);

        $output = $this->runRouter($router);

        $this->assertFalse($ran);
        $this->assertSame(HttpResponseCode::FORBIDDEN, $response->getStatusCode());
        $this->assertSame('Invalid request', $output);
    }

    public function testBlockingHonorsCustomStatusCode()
    {
        [$router, $response] = $this->routerFor('http://test.com/denied');
        $router->get('/denied', fn () => 'nope')->middleware(new NextBlockUnauthorizedMiddleware());

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::UNAUTHORIZED, $response->getStatusCode());
        $this->assertSame('Invalid request', $output);
    }

    public function testRedirectEmitsLocationAndStatus()
    {
        [$router, $response] = $this->routerFor('http://test.com/admin/dashboard');
        $ran = false;
        $router->get('/admin/dashboard', function () use (&$ran) {
            $ran = true;

            return 'Dashboard content';
        })->middleware(NextRedirectMiddleware::class);

        $output = $this->runRouter($router);

        $this->assertFalse($ran);
        $this->assertSame(HttpResponseCode::MOVED_TEMPORARILY, $response->getStatusCode());
        $this->assertSame('/login', $response->getHeader('Location'));
        $this->assertSame('', $output);
    }

    public function testForbiddenResponseEmitsStatusBodyAndContentType()
    {
        [$router, $response] = $this->routerFor('http://test.com/form', 'POST');
        $ran = false;
        $router->post('/form', function () use (&$ran) {
            $ran = true;

            return 'controller';
        })->middleware(NextForbiddenMiddleware::class);

        $output = $this->runRouter($router);

        $this->assertFalse($ran);
        $this->assertSame(HttpResponseCode::FORBIDDEN, $response->getStatusCode());
        $this->assertSame('blocked csrf', $output);
        $this->assertTrue($response->hasHeaderContentType());
        $this->assertStringContainsString('text/html', (string) $response->getHeader('Content-Type'));
    }

    public function testArrayReturnEmitsJson()
    {
        [$router, $response] = $this->routerFor('http://test.com/json');
        $router->get('/json', fn () => 'unreached')->middleware(NextJsonMiddleware::class);

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::OK, $response->getStatusCode());
        $this->assertSame(['ok' => true], json_decode($output, true));
        $this->assertTrue($response->hasHeaderContentType());
        $this->assertStringContainsString('application/json', (string) $response->getHeader('Content-Type'));
    }

    public function testHandlerArrayReturnFlowsThroughPipeline()
    {
        [$router, $response] = $this->routerFor('http://test.com/data');
        $router->get('/data', fn () => ['name' => 'John'])->middleware(NextPassMiddleware::class);

        $output = $this->runRouter($router);

        $this->assertSame(['name' => 'John'], json_decode($output, true));
    }

    public function testHandlerResponseFlowsThroughPipeline()
    {
        [$router, $response] = $this->routerFor('http://test.com/handler-response');
        $router->get('/handler-response', function () {
            $inner = new Response();
            $inner->setStatusCode(HttpResponseCode::CREATED);
            $inner->setHeader('X-Handler', '1');
            $inner->renderBody('handler response');

            return $inner;
        })->middleware(NextPassMiddleware::class);

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::CREATED, $response->getStatusCode());
        $this->assertSame('1', $response->getHeader('X-Handler'));
        $this->assertSame('handler response', $output);
    }

    public function testStringHijackShortCircuits()
    {
        [$router, $response] = $this->routerFor('http://test.com/hijack');
        $ran = false;
        $router->get('/hijack', function () use (&$ran) {
            $ran = true;

            return 'original';
        })->middleware(NextStringHijackMiddleware::class);

        $output = $this->runRouter($router);

        $this->assertFalse($ran);
        $this->assertSame('hijacked', $output);
    }

    public function testTrueAndNullContinueChain()
    {
        [$router, $response] = $this->routerFor('http://test.com/true');
        $router->get('/true', fn () => 'continued')->middleware(NextTrueMiddleware::class);
        $output = $this->runRouter($router);
        $this->assertSame('continued', $output);

        [$nullRouter, $nullResponse] = $this->routerFor('http://test.com/null');
        $nullRouter->get('/null', fn () => 'null continued')->middleware(NextNullMiddleware::class);
        $nullOutput = $this->runRouter($nullRouter);
        $this->assertSame('null continued', $nullOutput);
    }

    public function testHeaderPostProcessing()
    {
        [$router, $response] = $this->routerFor('http://test.com/secure');
        $router->get('/secure', fn () => 'secure body')->middleware(NextHeaderPostMiddleware::class);

        $output = $this->runRouter($router);

        $this->assertSame('secure body', $output);
        $this->assertSame('1', $response->getHeader('X-Auth-Checked'));
    }

    public function testThrowingMiddlewareEmitsSingle500()
    {
        [$router, $response] = $this->routerFor('http://test.com/throwing');
        $router->get('/throwing', fn () => 'unreached')->middleware(NextThrowMiddleware::class);

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertSame('Middleware Error', $output);
    }

    public function testMixedLegacyAndNewChain()
    {
        [$router, $response] = $this->routerFor('http://test.com/chain');
        $router->get('/chain', fn () => 'chain ok')
            ->middleware(NextLegacyAllow::class)
            ->middleware(NextPassMiddleware::class)
            ->middleware(new NextWithDependencyMiddleware());

        $output = $this->runRouter($router);

        $this->assertSame('chain ok', $output);
        $this->assertSame(HttpResponseCode::OK, $response->getStatusCode());
    }

    public function testMixedChainLegacyBlockStopsNew()
    {
        [$router, $response] = $this->routerFor('http://test.com/stopped');
        $ran = false;
        $router->get('/stopped', function () use (&$ran) {
            $ran = true;

            return 'nope';
        })->middleware(NextOrderOuterMiddleware::class)->middleware(NextLegacyBlock::class);

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::FORBIDDEN, $response->getStatusCode());
        $this->assertSame('Invalid request', $output);
        $this->assertFalse($ran);
        $this->assertSame(['M1-pre', 'M1-post'], NextTrace::$log);
    }

    public function testMissingMiddlewareEmitsSingle500InMixedChain()
    {
        [$router, $response] = $this->routerFor('http://test.com/bad');
        $router->get('/bad', fn () => 'nope')
            ->middleware(NextPassMiddleware::class)
            ->middleware('Missing_Next_Middleware_XYZ');

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertSame("Middleware Missing_Next_Middleware_XYZ doesn't exist or is invalid", $output);
    }

    public function testResponseStatusWinsOverStatusCodeProperty()
    {
        [$router, $response] = $this->routerFor('http://test.com/wins');
        $router->get('/wins', fn () => 'nope')->middleware(NextStatusWinsMiddleware::class);

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::FORBIDDEN, $response->getStatusCode());
        $this->assertSame('forbidden body', $output);
    }

    public function testResponseHeaderStorage()
    {
        $response = new Response();
        $response->setHeader('X-Custom', 'yes');

        $this->assertSame('yes', $response->getHeader('x-custom'));
        $this->assertTrue($response->hasHeader('X-CUSTOM'));

        $response->redirect('/target');

        $this->assertSame('/target', $response->getHeader('Location'));
    }

    public function testControllerFalseViaPassthroughIsNotABlock()
    {
        [$router, $response] = $this->routerFor('http://test.com/false');
        $router->get('/false', fn () => false)->middleware(NextPassMiddleware::class);

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::OK, $response->getStatusCode());
        $this->assertSame('', $output);
    }

    public function testLegacyThrowInsideMixedChainEmitsSingle500()
    {
        [$router, $response] = $this->routerFor('http://test.com/legacy-throw');
        $ran = false;
        $router->get('/legacy-throw', function () use (&$ran) {
            $ran = true;

            return 'unreached';
        })->middleware(NextPassMiddleware::class)->middleware(NextLegacyThrowMiddleware::class);

        $output = $this->runRouter($router);

        $this->assertFalse($ran);
        $this->assertSame(HttpResponseCode::INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertSame('Middleware Error', $output);
    }

    public function testHandlerThrowInsidePipelineEmitsSingle500()
    {
        [$router, $response] = $this->routerFor('http://test.com/handler-throw');
        $router->get('/handler-throw', 'NextThrowingController@boom')->middleware(NextPassMiddleware::class);

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertSame('Middleware Error', $output);
    }

    public function testRedirectRouteWithMiddlewareEmitsRedirect()
    {
        [$router, $response] = $this->routerFor('http://test.com/old');
        $router->redirect('/old', '/new')->middleware(NextPassMiddleware::class);

        $output = $this->runRouter($router);

        $this->assertSame(HttpResponseCode::MOVED_PERMANENTLY, $response->getStatusCode());
        $this->assertSame('/new', $response->getHeader('Location'));
        $this->assertSame('', $output);
    }
}
