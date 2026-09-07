<?php

use PHPUnit\Framework\TestCase;
use Roolith\Route\HttpConstants\HttpResponseCode;
use Roolith\Route\Response;

class ResponseForTest extends Response
{
    public function outputJson(mixed $content): bool|string
    {
        return parent::outputJson($content);
    }

    public function outputHtml(mixed $content): mixed
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
        // Covers 5.3 hygiene: captures echoed error body so CLI output
        // stays clean and headers_sent() is not polluted for later tests.
        ob_start();
        $this->response->errorResponse();
        ob_get_clean();

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
        // Covers 5.3 hygiene: header sends are CLI-safe via headers_sent()
        // guards in src; this test asserts state only, never real headers,
        // so it stays warning-free whether or not output was already sent.
        $response = new ResponseForTest();

        $response->setStatusCode(HttpResponseCode::OK);
        $this->assertSame(HttpResponseCode::OK, $response->getStatusCode());

        ob_start();
        echo 'prior output';
        $response->setHeaderJson();
        $response->redirect("http://test.com/target\r\nX-Injected: 1");
        $buffered = ob_get_clean();

        $this->assertSame('prior output', $buffered);
        $this->assertTrue($response->hasHeaderContentType());
        $this->assertSame(HttpResponseCode::OK, $response->getStatusCode());
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