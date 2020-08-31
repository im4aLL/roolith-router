<?php

use PHPUnit\Framework\TestCase;
use Roolith\Route\HttpConstants\HttpResponseCode;
use Roolith\Route\Response;

class ResponseForTest extends Response
{
    public function outputJson($content)
    {
        return parent::outputJson($content);
    }

    public function outputHtml($content)
    {
        return parent::outputHtml($content);
    }
}

class ResponseTest extends TestCase
{
    private $response;

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

        $this->assertSame(HttpResponseCode::NOT_FOUND, $this->response->getStatusCode());
    }
}