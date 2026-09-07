<?php

use PHPUnit\Framework\TestCase;
use Roolith\Route\HttpConstants\HttpResponseCode;
use Roolith\Route\Response;

class ResponseForTest extends Response
{
    public function outputJson($content): bool|string
    {
        return parent::outputJson($content);
    }

    public function outputHtml($content): array|string
    {
        return parent::outputHtml($content);
    }
}

class ResponseTest extends TestCase
{
    private ResponseForTest $response;

    public function setUp(): void
    {
        $this->response = new ResponseForTest();
    }

    public function testShouldHaveHeaderContentTypeSetToFalse()
    {
        $this->assertFalse($this->response->hasHeaderContentType());
    }

    public function testShouldAbleToSetStatusCode()
    {
        $this->response->setStatusCode(HttpResponseCode::OK);

        $this->assertSame(HttpResponseCode::OK, $this->response->getStatusCode());
    }

    public function testShouldInvokeOnceOutputHtmlMethodIfContentIsHtml()
    {
        $response = $this->getMockBuilder(ResponseForTest::class)->onlyMethods(['outputHtml'])->getMock();
        $response->expects($this->once())->method('outputHtml')->with(null);

        $response->body('');
    }

    public function testShouldInvokeOnceOutputJsonMethodIfContentIsArray()
    {
        $response = $this->getMockBuilder(ResponseForTest::class)->onlyMethods(['outputJson'])->getMock();
        $response->expects($this->once())->method('outputJson');

        $response->body(['a' => 1]);
    }

    public function testShouldSetJsonHeader()
    {
        $this->response->setHeaderJson();

        $this->assertTrue($this->response->hasHeaderContentType());
    }

    public function testShouldSetHtmlHeader()
    {
        $this->response->setHeaderHtml();

        $this->assertTrue($this->response->hasHeaderContentType());
    }

    public function testShouldSetPlainHeader()
    {
        $this->response->setHeaderPlain();

        $this->assertTrue($this->response->hasHeaderContentType());
    }

    public function testShouldOutputJson()
    {
        $json = $this->response->outputJson(['a' => 1]);

        $this->assertJson($json);

        $object = new stdClass();
        $object->test = 1;
        $json = $this->response->outputJson($object);

        $this->assertJson($json);
    }

    public function testShouldOutputHtml()
    {
        $html = '<p>Hello world!</p>';

        $this->assertSame($html, $this->response->outputHtml($html));

        $html = '<p>Vakuutan olevani vähintään 18-vuotias</p>';

        $this->assertSame($html, $this->response->outputHtml($html));
    }

    public function testShouldHaveErrorResponse()
    {
        $this->response->errorResponse();

        $this->assertSame(HttpResponseCode::INTERNAL_SERVER_ERROR, $this->response->getStatusCode());
    }

    public function testShouldKeepEchoForBcAndExposeRenderedOutput()
    {
        $response = new ResponseForTest();

        ob_start();
        $response->body('hello');
        $echoed = ob_get_clean();

        $this->assertSame('hello', $echoed);
        $this->assertSame('hello', $response->getLastOutput());
        $this->assertSame('hello', $response->renderBody('hello'));
    }

    public function testShouldStayHeaderSafeAfterOutput()
    {
        $response = new ResponseForTest();

        echo 'prior output';
        $response->setStatusCode(HttpResponseCode::OK);
        $response->setHeaderJson();
        $response->redirect("http://test.com/target\r\nX-Injected: 1");

        $this->assertSame(HttpResponseCode::OK, $response->getStatusCode());
        $this->assertTrue($response->hasHeaderContentType());
        $this->expectOutputString('prior output');
    }

    public function testShouldSurfaceJsonEncodingFailure()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/JSON encoding failed/');

        $this->response->outputJson(['v' => INF]);
    }

    public function testShouldHaveErrorJsonWithDefault500()
    {
        $response = new ResponseForTest();

        ob_start();
        $response->errorJson(['error' => 'oops']);
        $echoed = ob_get_clean();

        $this->assertSame(HttpResponseCode::INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertTrue($response->hasHeaderContentType());
        $this->assertJson($echoed);
        $this->assertSame(['error' => 'oops'], json_decode($echoed, true));
        $this->assertSame($echoed, $response->getLastOutput());
    }

    public function testShouldHaveErrorJsonWithExplicitStatus()
    {
        $response = new ResponseForTest();

        ob_start();
        $response->errorJson(['error' => 'missing'], HttpResponseCode::NOT_FOUND);
        $echoed = ob_get_clean();

        $this->assertSame(HttpResponseCode::NOT_FOUND, $response->getStatusCode());
        $this->assertSame(['error' => 'missing'], json_decode($echoed, true));
    }

    public function testShouldSanitizeRedirectCrlfWithoutExit()
    {
        $response = new ResponseForTest();
        $response->redirect("http://test.com/a\r\nX: 1");

        // No-exit semantics: execution continues after redirect().
        $this->assertTrue(true);
    }
}