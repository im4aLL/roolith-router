<?php
use PHPUnit\Framework\TestCase;
use Roolith\HttpConstants\HttpMethod;
use Roolith\Router;

class RouterTest extends TestCase
{
    private $router;

    public function setUp(): void
    {
        $this->router = new Router();
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
                    $matched = $searchSettings[$key] == $value ? true : false;

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

        $this->assertEquals(count($methodArray), count($routes));

        for ($i = 0; $i < count($routes); $i++) {
            $this->assertEquals($methodArray[$i], $routes[$i]['method']);
        }
    }

    public function testShouldAbleToAddCrudRoute()
    {
        $url = '/crud';
        $controllerName = 'TestController';

        $this->router->crud($url, $controllerName);
        $routes = $this->router->getRouteList();

        // total routes will be 8
        $this->assertEquals(8, count($routes));
        // should have default route
//        $defaultRoute = ['path' => ];

        // should have create route
        // should have show route
        // should have edit route
        // should have post route
        // should have update route
        // should have delete route
    }
}
