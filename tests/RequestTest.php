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

    public function isSecure(): bool
    {
        return parent::isSecure();
    }

    public function cleanQueryValue($string): array|string|null
    {
        return parent::cleanQueryValue($string);
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

    public function testShouldNormalizeLowercaseRequestMethod()
    {
        $previous = $_SERVER['REQUEST_METHOD'] ?? null;
        $_SERVER['REQUEST_METHOD'] = 'get';

        try {
            $request = new Request();

            $this->assertEquals(HttpMethod::GET, $request->getRequestMethod());
        } finally {
            if ($previous === null) {
                unset($_SERVER['REQUEST_METHOD']);
            } else {
                $_SERVER['REQUEST_METHOD'] = $previous;
            }
        }
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

    public function testShouldBuildIdenticalRequestedUrlForBareAndTrailingSlashBase()
    {
        $makeRequest = function (string $base): string {
            $request = $this->getMockBuilder(RequestForTest::class)->onlyMethods(['getCurrentUrl'])->getMock();
            $request->method('getCurrentUrl')->willReturn('http://test.com/test/another/');
            $request->setBaseUrl($base);

            return $request->getRequestedUrl();
        };

        $this->assertSame('/test/another', $makeRequest('http://test.com'));
        $this->assertSame($makeRequest('http://test.com'), $makeRequest('http://test.com/'));
    }

    public function testShouldStripBaseUrlOnlyAsLeadingPrefix()
    {
        $request = $this->getMockBuilder(RequestForTest::class)->onlyMethods(['getCurrentUrl'])->getMock();
        $request->method('getCurrentUrl')->willReturn('http://example.com/http://example.com/page');
        $request->setBaseUrl('http://example.com');

        // Only the leading prefix is stripped; the second occurrence stays
        // (cleaned per-segment, ':' preserved per 3.3).
        $this->assertSame('/http:/example.com/page', $request->getRequestedUrl());
    }

    public function testShouldResolveToRootWhenServerVarsAbsentAndBaseSet()
    {
        $host = $_SERVER['HTTP_HOST'] ?? null;
        $uri = $_SERVER['REQUEST_URI'] ?? null;
        $https = $_SERVER['HTTPS'] ?? null;
        unset($_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'], $_SERVER['HTTPS']);

        try {
            $request = new RequestForTest();
            $request->setBaseUrl('http://test.com');

            $this->assertSame('http://test.com', $request->getCurrentUrl());
            $this->assertSame('/', $request->getRequestedUrl());
        } finally {
            if ($host !== null) {
                $_SERVER['HTTP_HOST'] = $host;
            }

            if ($uri !== null) {
                $_SERVER['REQUEST_URI'] = $uri;
            }

            if ($https !== null) {
                $_SERVER['HTTPS'] = $https;
            }
        }
    }

    public function testShouldFallbackToLocalhostWhenServerVarsAbsentAndNoBase()
    {
        $host = $_SERVER['HTTP_HOST'] ?? null;
        $uri = $_SERVER['REQUEST_URI'] ?? null;
        unset($_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI']);

        try {
            $request = new RequestForTest();

            $this->assertSame('http://localhost/', $request->getCurrentUrl());
        } finally {
            if ($host !== null) {
                $_SERVER['HTTP_HOST'] = $host;
            }

            if ($uri !== null) {
                $_SERVER['REQUEST_URI'] = $uri;
            }
        }
    }

    public function testShouldPreserveDottedAndSpecialCharsPerMatcher()
    {
        $this->assertSame('a.txt', $this->request->cleanUrlString('a.txt'));
        $this->assertSame('v1.2', $this->request->cleanUrlString('v1.2'));
        $this->assertSame('a%20b+c:d@e~f,g', $this->request->cleanUrlString('a%20b+c:d@e~f,g'));
        $this->assertSame('café-2026', $this->request->cleanUrlString('café-2026'));
        $this->assertSame('abc123', $this->request->cleanUrlString('abc123!'));
    }

    public function testShouldStripQueryAndFragmentPerSegment()
    {
        $this->assertSame('page', $this->request->cleanUrlStringArray('page?x=1'));
        $this->assertSame('page', $this->request->cleanUrlStringArray('page#section'));
    }

    public function testShouldKeepUnicodeSlugEndToEnd()
    {
        $request = $this->getMockBuilder(RequestForTest::class)->onlyMethods(['getCurrentUrl'])->getMock();
        $request->method('getCurrentUrl')->willReturn('http://test.com/post/café-2026');
        $request->setBaseUrl('http://test.com');

        $this->assertSame('/post/café-2026', $request->getRequestedUrl());
    }

    public function testShouldFilterQueryValuesPerContext()
    {
        $_GET['q'] = 'hello world';

        try {
            $request = new Request();

            $this->assertSame('hello world', $request->getUrlParam('q'));
            $this->assertNull($request->getUrlParam('missing'));
        } finally {
            unset($_GET['q']);
        }
    }

    public function testShouldTreatHttpsOneAsSecure()
    {
        $previous = $_SERVER;
        $_SERVER['HTTPS'] = '1';

        try {
            $this->assertTrue((new RequestForTest())->isSecure());
        } finally {
            $_SERVER = $previous;
        }

        $_SERVER['HTTPS'] = 'on';

        try {
            $this->assertTrue((new RequestForTest())->isSecure());
        } finally {
            $_SERVER = $previous;
        }
    }

    public function testShouldTreatForwardedProtoAsSecure()
    {
        $previous = $_SERVER;
        unset($_SERVER['HTTPS']);
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';

        try {
            $this->assertTrue((new RequestForTest())->isSecure());
        } finally {
            $_SERVER = $previous;
        }
    }
}
