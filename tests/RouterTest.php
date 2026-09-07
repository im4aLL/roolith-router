<?php
use PHPUnit\Framework\TestCase;
use Roolith\Route\HttpConstants\HttpMethod;
use Roolith\Route\Request;
use Roolith\Route\Router;

class RouterForTest extends Router
{
    public function getRequestedRouter($path, $method): mixed
    {
        return parent::getRequestedRouter($path, $method);
    }

    public function matchPattern($routerPath, $url): bool|array
    {
        return parent::matchPattern($routerPath, $url);
    }

    public static function joinUrl(string $base, string $path): string
    {
        return parent::joinUrl($base, $path);
    }
    
    public function executeRouteMethod($router): static
    {
        return parent::executeRouteMethod($router);
    }
}

class RouterTest extends TestCase
{
    private Router $router;
    private string $url;
    private string $controllerName;

    public function setUp(): void
    {
        $this->router = new Router();
        $this->url = '/test';
        $this->controllerName = 'TestController';
    }

    public function tearDown(): void
    {
        $this->router = new Router();
    }

    protected function getLastRoute()
    {
        $routerList = $this->router->getRouteList();
        return end($routerList);
    }

    protected function findItemsInArray($items, $searchSettings, $singleResult = false)
    {
        $result = $singleResult ? false : [];

        foreach ($items as $item) {
            $matched = false;

            foreach ($item as $key => $value) {
                if (isset($searchSettings[$key])) {
                    $matched = $searchSettings[$key] == $value;

                    if (!$matched) {
                        break;
                    }
                }
            }

            if ($matched) {
                if ($singleResult) {
                    $result = $item;
                    break;
                } else {
                    $result[] = $item;
                }
            }
        }

        return $result;
    }

    protected function findItemInArray($items, $searchSettings)
    {
        return $this->findItemsInArray($items, $searchSettings, true);
    }

    public function testShouldInitializeRouterArray()
    {
        $this->assertIsArray($this->router->getRouteList());
    }

    public function testShouldHaveGroupSettingsArray()
    {
        $this->assertFalse($this->router->getGroupSettings());
    }

    public function testShouldAbleToSetGroupSettings()
    {
        $this->router->setGroupSettings(['urlPrefix' => 'prefix']);

        $this->assertIsArray($this->router->getGroupSettings());
    }

    public function testShouldAbleToResetGroupSettings()
    {
        $this->router->setGroupSettings(['urlPrefix' => 'prefix']);
        $this->router->resetGroupSettings();

        $this->assertFalse($this->router->getGroupSettings());
    }

    public function testShouldAbleToAddGetRoute()
    {
        $this->router->get('/', function() {
            return 'done';
        });

        $route = $this->getLastRoute();

        $this->assertEquals(HttpMethod::GET, $route['method']);
    }

    public function testShouldAbleToAddPostRoute()
    {
        $this->router->post('/', function() {
            return 'done';
        });

        $route = $this->getLastRoute();

        $this->assertEquals(HttpMethod::POST, $route['method']);
    }

    public function testShouldAbleToAddPutRoute()
    {
        $this->router->put('/', function() {
            return 'done';
        });

        $route = $this->getLastRoute();

        $this->assertEquals(HttpMethod::PUT, $route['method']);
    }

    public function testShouldAbleToAddPatchRoute()
    {
        $this->router->patch('/', function() {
            return 'done';
        });

        $route = $this->getLastRoute();

        $this->assertEquals(HttpMethod::PATCH, $route['method']);
    }

    public function testShouldAbleToAddDeleteRoute()
    {
        $this->router->delete('/', function() {
            return 'done';
        });

        $route = $this->getLastRoute();

        $this->assertEquals(HttpMethod::DELETE, $route['method']);
    }

    public function testShouldAbleToAddOptionsRoute()
    {
        $this->router->options('/', function() {
            return 'done';
        });

        $route = $this->getLastRoute();

        $this->assertEquals(HttpMethod::OPTIONS, $route['method']);
    }

    public function testShouldAbleToAddMultipleMethodRouteAtOnce()
    {
        $methodArray = ['GET', 'POST'];
        $this->router->match($methodArray, '/', function() {
            return 'done';
        });

        $routes = $this->router->getRouteList();

        for ($i = 0; $i < count($routes); $i++) {
            $this->assertEquals($methodArray[$i], $routes[$i]['method']);
        }
    }

    public function testShouldAbleToAddWildcardRoute()
    {
        $this->router->any('/', function() {
            return 'done!';
        });

        $methodArray = HttpMethod::all();
        $routes = $this->router->getRouteList();

        $this->assertSameSize($methodArray, $routes);

        for ($i = 0; $i < count($routes); $i++) {
            $this->assertEquals($methodArray[$i], $routes[$i]['method']);
        }
    }

    private function crudRouteSetUp()
    {
        $this->router->crud($this->url, $this->controllerName);
        return $this->router->getRouteList();
    }

    public function testShouldAbleToAddCrudRoute()
    {
        $routes = $this->crudRouteSetUp();

        $this->assertCount(10, $routes);
    }

    public function testShouldHaveDefaultRouteForCrud()
    {
        $routes = $this->crudRouteSetUp();

        $expectedRoute = ['path' => $this->url, 'method' => HttpMethod::GET, 'name' => ltrim($this->url, '/').'.index'];
        $actualRoute = $this->findItemInArray($routes, $expectedRoute);

        $this->assertIsArray($actualRoute);
    }

    public function testShouldHaveCreateRouteForCrud()
    {
        $routes = $this->crudRouteSetUp();

        $expectedRoute = ['path' => $this->url.'/create', 'method' => HttpMethod::GET, 'name' => ltrim($this->url, '/').'.create'];
        $actualRoute = $this->findItemInArray($routes, $expectedRoute);

        $this->assertIsArray($actualRoute);
    }

    public function testShouldHaveShowRouteForCrud()
    {
        $routes = $this->crudRouteSetUp();

        $expectedRoute = ['path' => $this->url.'/{param}', 'method' => HttpMethod::GET, 'name' => ltrim($this->url, '/').'.show'];
        $actualRoute = $this->findItemInArray($routes, $expectedRoute);

        $this->assertIsArray($actualRoute);
    }

    public function testShouldHaveEditRouteForCrud()
    {
        $routes = $this->crudRouteSetUp();

        $expectedRoute = ['path' => $this->url.'/{param}/edit', 'method' => HttpMethod::GET, 'name' => ltrim($this->url, '/').'.edit'];
        $actualRoute = $this->findItemInArray($routes, $expectedRoute);

        $this->assertIsArray($actualRoute);
    }

    public function testShouldHavePostRouteForCrud()
    {
        $routes = $this->crudRouteSetUp();

        $expectedRoute = ['path' => $this->url, 'method' => HttpMethod::POST, 'name' => ltrim($this->url, '/').'.store'];
        $actualRoute = $this->findItemInArray($routes, $expectedRoute);

        $this->assertIsArray($actualRoute);
    }

    public function testShouldHaveUpdateRouteForCrud()
    {
        $routes = $this->crudRouteSetUp();

        $expectedRoute = ['path' => $this->url.'/{param}', 'method' => HttpMethod::PUT, 'name' => ltrim($this->url, '/').'.update'];
        $actualRoute = $this->findItemInArray($routes, $expectedRoute);

        $this->assertIsArray($actualRoute);

        $expectedRoute = ['path' => $this->url.'/{param}', 'method' => HttpMethod::PATCH, 'name' => ltrim($this->url, '/').'.update'];
        $actualRoute = $this->findItemInArray($routes, $expectedRoute);

        $this->assertIsArray($actualRoute);
    }

    public function testShouldHaveDeleteRouteForCrud()
    {
        $routes = $this->crudRouteSetUp();

        $expectedRoute = ['path' => $this->url.'/{param}', 'method' => HttpMethod::DELETE, 'name' => ltrim($this->url, '/').'.destroy'];
        $actualRoute = $this->findItemInArray($routes, $expectedRoute);

        $this->assertIsArray($actualRoute);
    }

    public function testShouldAutomaticDefineMethodNameForControllerForCrud()
    {
        $routes = $this->crudRouteSetUp();

        foreach (['index', 'create', 'show', 'edit', 'store', 'update', 'destroy'] as $name) {
            $expectedRoute = ['execute' => $this->controllerName.'@'.$name];
            $actualRoute = $this->findItemInArray($routes, $expectedRoute);

            $this->assertIsArray($actualRoute);
        }
    }

    public function testShouldAbleToAddRedirectRoute()
    {
        $this->router->redirect('/redirect', '/redirected');
        $route = $this->getLastRoute();

        $this->assertArrayHasKey('redirect', $route);
    }

    public function testShouldAbleToGroupRoutes()
    {
        $this->router->group(['middleware' => 'MiddlewareClassName', 'urlPrefix' => 'user/{userId}', 'namePrefix' => 'user.'], function () {
            $this->router->get('profile', function ($userId){
                return "profile route: User id: $userId";
            })->name('profile');

            $this->router->get('action/{actionId}', function ($userId, $actionId){
                return "action route: User id: $userId and action id $actionId";
            })->name('action');
        });

        $routes = $this->router->getRouteList();
        $this->assertCount(2, $routes);

        $expectedRoute = ['middleware' => ['MiddlewareClassName']];
        $actualRoutes = $this->findItemsInArray($routes, $expectedRoute);
        $this->assertCount(2, $actualRoutes);

        $expectedRoute = ['path' => '/user/{userId}/profile'];
        $actualRoutes = $this->findItemsInArray($routes, $expectedRoute);
        $this->assertCount(1, $actualRoutes);

        $expectedRoute = ['name' => 'user.action'];
        $actualRoutes = $this->findItemsInArray($routes, $expectedRoute);
        $this->assertCount(1, $actualRoutes);
    }

    public function testShouldMatchRouterByPath()
    {
        $router = new RouterForTest();
        $router->get('test', function() {
            return 'Done!';
        });

        $expectedRouter = $router->getRequestedRouter('/test', HttpMethod::GET);

        $this->assertEquals('/test', $expectedRouter['path']);
    }

    public function testShouldMatchRouterByPattern()
    {
        $router = new RouterForTest();
        $router->get('user/{id}', function() {
            return 'Done!';
        });

        $expectedRouter = $router->getRequestedRouter('/user/1', HttpMethod::GET);

        $this->assertEquals('/user/{id}', $expectedRouter['path']);
    }

    public function testShouldMatchPatternWithGivenUrl()
    {
        $router = new RouterForTest();

        $isMatch = $router->matchPattern('/user/{id}/edit', '/user/1/edit');
        $this->assertIsArray($isMatch);
        $this->assertEquals(1, $isMatch[0]);
    }

    public function testShouldAddNameToRoute()
    {
        $this->router->delete('/', function() {
            return 'done';
        })->name('delete');

        $route = $this->getLastRoute();

        $this->assertEquals('delete', $route['name']);
    }

    public function testShouldGetUrlByName()
    {
        $this->router->delete('/', function() {
            return 'done';
        })->name('delete');

        $url = $this->router->getUrlByName('delete');

        $this->assertIsString($url);
    }

    public function testShouldGetUrlByNameAndParam()
    {
        $this->router->delete('delete/{id}', function() {
            return 'done';
        })->name('delete');

        $url = $this->router->getUrlByName('delete', ['id' => 1]);

        // Chunk 3 fix 3.9: joinUrl normalizes the separator, so an empty
        // base yields an absolute path with a leading slash.
        $this->assertSame('/delete/1', $url);
    }

    public function testShouldAddMiddlewareToRoute()
    {
        $this->router->delete('/', function() {
            return 'done';
        })->middleware('DemoMiddleware');

        $route = $this->getLastRoute();

        $this->assertEquals(['DemoMiddleware'], $route['middleware']);
    }

    public function testShouldRouteRunCallExecuteRouteMethodOnce()
    {
        $request = $this->getMockBuilder(Request::class)->onlyMethods(['getCurrentUrl'])->getMock();
        $request->method('getCurrentUrl')->willReturn('http://habibhadi.com/');

        $router = $this->getMockBuilder(RouterForTest::class)->setConstructorArgs([[], null, $request])->onlyMethods(['executeRouteMethod'])->getMock();
        $router->expects($this->once())->method('executeRouteMethod')->with(null);

        $router->run();
    }

    public function testShouldMatchParamWithDot()
    {
        $router = new RouterForTest();
        $router->get('file/{name}', function() {
            return 'Done!';
        });

        $matchedRouter = $router->getRequestedRouter('/file/a.txt', HttpMethod::GET);

        $this->assertIsArray($matchedRouter);
        $this->assertEquals('/file/{name}', $matchedRouter['path']);
        $this->assertEquals(['a.txt'], $matchedRouter['payload']);
    }

    /**
     * Matcher-only coverage: exercises matchPattern() directly via
     * getRequestedRouter(). End-to-end dispatch of unicode slugs waits on
     * chunk 3 fix 3.3 (Request::cleanUrlString strips non-ASCII).
     */
    public function testShouldMatchParamWithUnicodeSlug()
    {
        $router = new RouterForTest();
        $router->get('post/{slug}', function() {
            return 'Done!';
        });

        // Matcher-only: bypasses Request::getRequestedUrl() sanitizer.
        $matchedRouter = $router->getRequestedRouter('/post/café-2026', HttpMethod::GET);

        $this->assertIsArray($matchedRouter);
        $this->assertEquals('/post/{slug}', $matchedRouter['path']);
        $this->assertEquals(['café-2026'], $matchedRouter['payload']);
    }

    public function testShouldNotMatchShorterUrl()
    {
        $router = new RouterForTest();

        $this->assertFalse($router->matchPattern('/user/{id}/edit', '/user/1'));
    }

    public function testShouldExpandMultiOptionalRoutesWithoutDuplicates()
    {
        $this->router->get('a/{b?}/c/{d?}', function() {
            return 'done';
        });

        $routes = $this->router->getRouteList();
        $paths = array_column($routes, 'path');

        $this->assertCount(3, $routes);
        $this->assertContains('/a', $paths);
        $this->assertContains('/a/{b}/c', $paths);
        $this->assertContains('/a/{b}/c/{d}', $paths);
        $this->assertNotContains('/a/c', $paths);
        $this->assertSame($paths, array_values(array_unique($paths)));
    }

    public function testShouldExpandTrailingOptionalRoute()
    {
        $this->router->get('name/{name?}', function() {
            return 'done';
        });

        $paths = array_column($this->router->getRouteList(), 'path');

        $this->assertEquals(['/name', '/name/{name}'], $paths);
    }

    public function testShouldMapLeadingOptionalEmptyPrefixToRoot()
    {
        $this->router->get('{lang?}/about', function() {
            return 'done';
        });

        $paths = array_column($this->router->getRouteList(), 'path');

        $this->assertEquals(['/', '/{lang}/about'], $paths);
    }

    public function testShouldNormalizeMatchMethodCaseAndIgnoreUnknown()
    {
        $this->router->match(['GET', 'post', 'BOGUS'], '/user', function() {
            return 'done';
        });

        $routes = $this->router->getRouteList();

        $this->assertCount(2, $routes);
        $this->assertEquals(HttpMethod::GET, $routes[0]['method']);
        $this->assertEquals(HttpMethod::POST, $routes[1]['method']);
    }

    public function testShouldMatchRouteWithLowercaseRequestMethod()
    {
        $previous = $_SERVER['REQUEST_METHOD'] ?? null;
        $_SERVER['REQUEST_METHOD'] = 'get';

        try {
            $router = new RouterForTest();
            $router->get('test', function() {
                return 'Done!';
            });

            $this->assertEquals(HttpMethod::GET, (new Request())->getRequestMethod());

            $matchedRouter = $router->getRequestedRouter('/test', (new Request())->getRequestMethod());

            $this->assertIsArray($matchedRouter);
            $this->assertEquals('/test', $matchedRouter['path']);
        } finally {
            if ($previous === null) {
                unset($_SERVER['REQUEST_METHOD']);
            } else {
                $_SERVER['REQUEST_METHOD'] = $previous;
            }
        }
    }

    public function testShouldApplyNameToWholeMatchSlice()
    {
        $this->router->match(['GET', 'POST'], '/user', function() {
            return 'done';
        })->name('user');

        $routes = $this->router->getRouteList();

        $this->assertCount(2, $routes);
        $this->assertEquals('user', $routes[0]['name']);
        $this->assertEquals('user', $routes[1]['name']);
    }

    public function testShouldApplyNameToWholeAnySlice()
    {
        $this->router->any('/user', function() {
            return 'done';
        })->name('user');

        $routes = $this->router->getRouteList();

        $this->assertCount(6, $routes);

        foreach ($routes as $route) {
            $this->assertEquals('user', $route['name']);
        }
    }

    public function testShouldApplyNameToWholeArrayParamSlice()
    {
        $this->router->get(['user', 'profile'], function() {
            return 'done';
        })->name('page');

        $routes = $this->router->getRouteList();

        $this->assertCount(2, $routes);
        $this->assertEquals('page', $routes[0]['name']);
        $this->assertEquals('page', $routes[1]['name']);
    }

    public function testShouldApplyMiddlewareToWholeMatchSlice()
    {
        $this->router->match(['GET', 'POST'], '/user', function() {
            return 'done';
        })->middleware('DemoMiddleware');

        $routes = $this->router->getRouteList();

        $this->assertCount(2, $routes);
        $this->assertEquals(['DemoMiddleware'], $routes[0]['middleware']);
        $this->assertEquals(['DemoMiddleware'], $routes[1]['middleware']);
    }

    public function testShouldMergeNestedGroups()
    {
        $router = $this->router;
        $router->group(['urlPrefix' => 'outer', 'namePrefix' => 'outer.', 'middleware' => 'OuterMiddleware'], function ($router) {
            $router->group(['urlPrefix' => 'inner', 'namePrefix' => 'inner.', 'middleware' => 'InnerMiddleware'], function ($router) {
                $router->get('p', function() {
                    return 'done';
                })->name('p');
            });
        });

        $routes = $router->getRouteList();

        $this->assertCount(1, $routes);
        $this->assertEquals('/outer/inner/p', $routes[0]['path']);
        $this->assertEquals('outer.inner.p', $routes[0]['name']);
        $this->assertEquals(['OuterMiddleware', 'InnerMiddleware'], $routes[0]['middleware']);
    }

    public function testShouldRestoreOuterGroupAfterInnerGroup()
    {
        $router = $this->router;
        $router->group(['urlPrefix' => 'outer', 'namePrefix' => 'outer.'], function ($router) {
            $router->group(['urlPrefix' => 'inner'], function ($router) {
                $router->get('a', function() {
                    return 'done';
                });
            });
            $router->get('b', function() {
                return 'done';
            });
        });

        $routes = $router->getRouteList();

        $this->assertCount(2, $routes);
        $this->assertEquals('/outer/inner/a', $routes[0]['path']);
        $this->assertEquals('/outer/b', $routes[1]['path']);
        $this->assertFalse($router->getGroupSettings());
    }

    public function testShouldSupportUseClosureInGroup()
    {
        $router = $this->router;
        $router->group(['urlPrefix' => 'legacy'], function () use ($router) {
            $router->get('p', function() {
                return 'done';
            });
        });

        $routes = $router->getRouteList();

        $this->assertCount(1, $routes);
        $this->assertEquals('/legacy/p', $routes[0]['path']);
    }

    public function testShouldAppendChainedMiddlewareAfterGroupMiddleware()
    {
        $router = $this->router;
        $router->group(['middleware' => 'GroupMiddleware'], function ($router) {
            $router->get('p', function() {
                return 'done';
            })->middleware('RouteMiddleware');
        });

        $routes = $router->getRouteList();

        $this->assertCount(1, $routes);
        $this->assertEquals(['GroupMiddleware', 'RouteMiddleware'], $routes[0]['middleware']);
    }

    public function testShouldNormalizeUrlPrefixConcat()
    {
        $router = $this->router;
        $router->group(['urlPrefix' => 'user/'], function ($router) {
            $router->get('/profile', function() {
                return 'done';
            });
        });

        $routes = $router->getRouteList();

        $this->assertCount(1, $routes);
        $this->assertEquals('/user/profile', $routes[0]['path']);
    }

    public function testShouldRegisterRedirectForAllMethods()
    {
        $this->router->redirect('/redirect', '/redirected');

        $routes = $this->router->getRouteList();

        $this->assertCount(6, $routes);
        $this->assertEquals(HttpMethod::all(), array_column($routes, 'method'));

        foreach ($routes as $route) {
            $this->assertEquals('/redirect', $route['path']);
            $this->assertEquals('/redirected', $route['redirect']);
        }
    }

    public function testShouldNotTreatHttpFooAsAbsoluteRedirect()
    {
        $this->router->redirect('/from', 'httpfoo');

        $route = $this->getLastRoute();

        $this->assertEquals('/httpfoo', $route['redirect']);
    }

    public function testShouldKeepAbsoluteRedirectUrl()
    {
        $this->router->redirect('/from', 'https://example.com/target');

        $route = $this->getLastRoute();

        $this->assertEquals('https://example.com/target', $route['redirect']);
    }

    public function testShouldApplyGroupSettingsToRedirect()
    {
        $router = $this->router;
        $router->group(['urlPrefix' => 'admin', 'namePrefix' => 'admin.'], function ($router) {
            $router->redirect('/old', '/new');
        });

        $routes = $router->getRouteList();

        $this->assertCount(6, $routes);

        foreach ($routes as $route) {
            $this->assertEquals('/admin/old', $route['path']);
            $this->assertEquals('admin.', $route['name']);
            $this->assertEquals('/new', $route['redirect']);
        }
    }

    public function testShouldJoinUrlsIdenticallyWithBareAndTrailingSlashBase()
    {
        $this->assertEquals(
            RouterForTest::joinUrl('http://test.com', 'redirected'),
            RouterForTest::joinUrl('http://test.com/', '/redirected')
        );
        $this->assertEquals('http://test.com/redirected', RouterForTest::joinUrl('http://test.com', 'redirected'));
        $this->assertEquals('/redirected', RouterForTest::joinUrl('', 'redirected'));
    }

    public function testShouldBuildIdenticalRedirectTargetsForBareAndSlashedBase()
    {
        $firstRouter = new Router(['base_url' => 'http://test.com']);
        $firstRouter->redirect('/a', 'target');

        $secondRouter = new Router(['base_url' => 'http://test.com/']);
        $secondRouter->redirect('/a', 'target');

        $firstRoutes = $firstRouter->getRouteList();
        $secondRoutes = $secondRouter->getRouteList();

        $this->assertEquals($firstRoutes[0]['redirect'], $secondRoutes[0]['redirect']);
        $this->assertEquals('http://test.com/target', $firstRoutes[0]['redirect']);
    }

    public function testShouldRegisterZeroPath()
    {
        $this->router->get('0', function() {
            return 'done';
        });

        $routes = $this->router->getRouteList();

        $this->assertCount(1, $routes);
        $this->assertEquals('/0', $routes[0]['path']);
    }

    public function testShouldNotRetagPreviousSliceOnNoOpRegistration()
    {
        $this->router->get('/a', function() {
            return 'done';
        })->name('first');

        $this->router->match(['BOGUS'], '/b', function() {
            return 'done';
        })->name('second');

        $routes = $this->router->getRouteList();

        $this->assertCount(1, $routes);
        $this->assertEquals('first', $routes[0]['name']);
        $this->assertEquals('/a', $routes[0]['path']);
    }

    public function testShouldTreatStaticDotAsLiteral()
    {
        $router = new RouterForTest();
        $router->get('/file/about.html', function() {
            return 'Done!';
        });

        $matched = $router->getRequestedRouter('/file/about.html', HttpMethod::GET);
        $this->assertIsArray($matched);
        $this->assertEquals('/file/about.html', $matched['path']);

        $this->assertNull($router->getRequestedRouter('/file/aboutXhtml', HttpMethod::GET));
    }

    public function testShouldKeepStaticQuestionMarkLiteral()
    {
        $this->router->get('/faq?help', function() {
            return 'done';
        });

        $routes = $this->router->getRouteList();

        $this->assertCount(1, $routes);
        $this->assertEquals('/faq?help', $routes[0]['path']);
    }

    public function testShouldBuildIdenticalNamedUrlsForBareAndSlashedBase()
    {
        $first = new Router(['base_url' => 'http://test.com']);
        $first->get('delete/{id}', function() {
            return 'done';
        })->name('delete');

        $second = new Router(['base_url' => 'http://test.com/']);
        $second->get('delete/{id}', function() {
            return 'done';
        })->name('delete');

        $firstUrl = $first->getUrlByName('delete', ['id' => 1]);
        $secondUrl = $second->getUrlByName('delete', ['id' => 1]);

        $this->assertSame($firstUrl, $secondUrl);
        $this->assertSame('http://test.com/delete/1', $firstUrl);
    }

    public function testShouldReturnBaseUnchangedWhenNameNotFound()
    {
        $router = new Router(['base_url' => 'http://test.com']);
        $router->get('/exists', function() {
            return 'done';
        })->name('exists');

        $this->assertSame('http://test.com', $router->getUrlByName('missing'));
    }

    public function testShouldReturnEmptyBaseUnchangedWhenNameNotFound()
    {
        $this->router->get('/exists', function() {
            return 'done';
        })->name('exists');

        $this->assertSame('', $this->router->getUrlByName('missing'));
    }

    public function testShouldUrlEncodePlaceholderValues()
    {
        $this->router->get('search/{q}', function() {
            return 'done';
        })->name('search');

        $url = $this->router->getUrlByName('search', ['q' => 'a b/c']);

        $this->assertSame('/search/a+b%2Fc', $url);
    }

    public function testShouldTolerateOptionalPlaceholderSyntax()
    {
        // Redirect sources keep literal '{x?}' (no optional expansion),
        // so getUrlByName must treat {key?} as {key}.
        $router = new Router();
        $router->group(['namePrefix' => 'r.'], function ($router) {
            $router->redirect('/a/{x?}', '/target');
        });

        $url = $router->getUrlByName('r.', ['x' => 'hadi']);

        $this->assertSame('/a/hadi', $url);
    }

    public function testShouldQuoteRegexCharsInPlaceholderKeys()
    {
        $router = new RouterForTest();
        $router->get('/a/{b.c}/d', function() {
            return 'done';
        })->name('dotted');

        // Key with regex char must match literally, not as 'any char'.
        $url = $router->getUrlByName('dotted', ['b.c' => '1']);

        $this->assertSame('/a/1/d', $url);
    }

}
