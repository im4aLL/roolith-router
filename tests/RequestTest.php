<?php

use PHPUnit\Framework\TestCase;
use Roolith\Route\HttpConstants\HttpMethod;
use Roolith\Route\Request;

class RequestForTest extends Request
{
    public function getCurrentUrl(): string
    {
        return parent::getCurrentUrl();
    }

    public function cleanUrlString($string): array|string|null
    {
        return parent::cleanUrlString($string);
    }

    public function cleanUrlStringArray($string): array|string|null
    {
        return parent::cleanUrlStringArray($string);
    }
}

class RequestTest extends TestCase
{
    private RequestForTest $request;

    public function setUp(): void
    {
        $this->request = $this->mockRequestClass();
    }

    private function mockRequestClass(): RequestForTest
    {
        $request = $this->getMockBuilder(RequestForTest::class)->onlyMethods(['getCurrentUrl'])->getMock();
        $request->method('getCurrentUrl')
            ->willReturn('http://habibhadi.com/');

        return $request;
    }

    public function testShouldGetCurrentUrl()
    {
        $this->assertEquals('http://habibhadi.com/', $this->request->getCurrentUrl());
    }

    public function testShouldAbleToSetBaseUrl()
    {
        $this->request->setBaseUrl('http://test.com');

        $this->assertEquals('http://test.com', $this->request->getBaseUrl());
    }

    public function testShouldGetRequestedMethod()
    {
        $this->assertEquals(HttpMethod::GET, $this->request->getRequestMethod());

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->request->__construct();
        $this->assertEquals(HttpMethod::POST, $this->request->getRequestMethod());
    }

    public function testShouldGetRequestedUrlWithoutBaseUrl()
    {
        $request = $this->getMockBuilder(RequestForTest::class)->onlyMethods(['getCurrentUrl'])->getMock();
        $request->method('getCurrentUrl')
            ->willReturn('http://habibhadi.com/test/another/');

        $request->setBaseUrl('http://habibhadi.com/');

        $this->assertEquals('/test/another', $request->getRequestedUrl());
    }

    public function testShouldRemoveNonAllowedCharacterFromUrlString()
    {
        $this->assertEquals('abc123', $this->request->cleanUrlString('abc123'));
        $this->assertEquals('abc123', $this->request->cleanUrlString('abc123!'));
        $this->assertEquals('abc123.', $this->request->cleanUrlString('abc123.!'));
        $this->assertEquals('abc123.-', $this->request->cleanUrlString('abc123.!-'));
    }

    public function testShouldAbleToSetAndGetRequestParam()
    {
        $paramArray = ['{name}', '{id}'];
        $paramValueArray = ['hadi', 1];

        $this->request->setRequestedParam($paramArray, $paramValueArray);
        $nameParam = $this->request->getParam('name');

        $this->assertEquals('hadi', $nameParam);
    }
}
