<?php
use PHPUnit\Framework\TestCase;
use Roolith\HttpConstants\HttpMethod;
use Roolith\Router;

class DemoRouter extends Router
{
    public function getRequestedRouter($path, $method)
    {
        return parent::getRequestedRouter($path, $method);
    }

    public function matchPlain($routerPath, $url)
    {
        return parent::matchPlain($routerPath, $url);
    }

    public function matchPattern($routerPath, $url)
    {
        return parent::matchPattern($routerPath, $url);
    }
}

class RouterTest extends TestCase
{
    private $router;
    private $url;
    private $controllerName;

    public function setUp(): void
    {
        $this->router = new Router();
        $this->url = '/test';
        $this->controllerName = 'TestController';
    }

    public function tearDown(): void
    {
        $this->router = null;
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

        $this->assertCount(8, $routes);
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

        $expectedRoute = ['middleware' => 'MiddlewareClassName'];
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
        $router = new DemoRouter();
        $router->get('test', function() {
            return 'Done!';
        });

        $expectedRouter = $router->getRequestedRouter('/test', HttpMethod::GET);

        $this->assertEquals('/test', $expectedRouter['path']);
    }

    public function testShouldMatchRouterByPattern()
    {
        $router = new DemoRouter();
        $router->get('user/{id}', function() {
            return 'Done!';
        });

        $expectedRouter = $router->getRequestedRouter('/user/1', HttpMethod::GET);

        $this->assertEquals('/user/{id}', $expectedRouter['path']);
    }

    public function testShouldMatchPatternWithGivenUrl()
    {
        $router = new DemoRouter();

        $isMatch = $router->matchPlain('/user/{id}/edit', '/user/1/edit');
        $this->assertIsArray($isMatch);
        $this->assertEquals(1, $isMatch[0]);

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

    public function testShouldAddMiddlewareToRoute()
    {
        $this->router->delete('/', function() {
            return 'done';
        })->middleware('DemoMiddleware');

        $route = $this->getLastRoute();

        $this->assertEquals('DemoMiddleware', $route['middleware']);
    }

}
