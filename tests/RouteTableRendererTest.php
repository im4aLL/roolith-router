<?php
use PHPUnit\Framework\TestCase;
use Roolith\Route\RouteTableRenderer;
use Roolith\Route\Router;

class RouteTableRendererTest extends TestCase
{
    public function testShouldReturnNoticeForEmptyRouteList()
    {
        $this->assertSame("No routes registered.\n", RouteTableRenderer::render([]));
    }

    public function testShouldRenderTableWithHeadersAndRows()
    {
        $router = new Router();
        $router->get('/', 'Demo\Controller@index');
        $router->get('/{id}', 'Demo\Controller@simpleIndex');

        $output = $router->formattedRouteList();

        $this->assertStringContainsString('Method', $output);
        $this->assertStringContainsString('Path', $output);
        $this->assertStringContainsString('Name', $output);
        $this->assertStringContainsString('Action', $output);
        $this->assertStringContainsString('Middleware', $output);
        $this->assertStringContainsString('Demo\Controller@index', $output);
        $this->assertStringContainsString('Demo\Controller@simpleIndex', $output);
    }

    public function testShouldRenderClosureMiddlewareAndRedirect()
    {
        $router = new Router();
        $router->get('/admin', function () {
            return 'x';
        })->name('admin.index')->middleware('Demo\AuthMiddleware');
        $router->redirect('/old', '/new');

        $output = $router->formattedRouteList();

        $this->assertStringContainsString('Closure', $output);
        $this->assertStringContainsString('admin.index', $output);
        $this->assertStringContainsString('Demo\AuthMiddleware', $output);
        $this->assertStringContainsString('Redirect to /new (301)', $output);
    }

    public function testShouldJoinMultipleMiddlewareWithComma()
    {
        $output = RouteTableRenderer::render([
            ['method' => 'GET', 'path' => '/p', 'name' => '-', 'execute' => 'C@m', 'middleware' => ['One', 'Two']],
        ]);

        $this->assertStringContainsString('One, Two', $output);
    }
}
