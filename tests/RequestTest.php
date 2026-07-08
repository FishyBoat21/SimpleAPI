<?php

namespace Fishyboat21\SimpleApi\Tests;

use Fishyboat21\SimpleApi\Http\Request;
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    public function testFromParts(): void
    {
        $request = Request::fromParts('GET', '/api/health?foo=bar', ['foo' => 'bar']);
        $this->assertSame('GET', $request->method());
        $this->assertSame('/api/health?foo=bar', $request->uri());
        $this->assertSame('/api/health', $request->path());
        $this->assertSame('bar', $request->query('foo'));
    }

    public function testPathCached(): void
    {
        $request = Request::fromParts('GET', '/test/path');
        $this->assertSame('/test/path', $request->path());
        // Called twice — uses cache
        $this->assertSame('/test/path', $request->path());
    }

    public function testQueryDefault(): void
    {
        $request = Request::fromParts('GET', '/api/health');
        $this->assertNull($request->query('missing'));
        $this->assertSame('default', $request->query('missing', 'default'));
    }

    public function testQueryParams(): void
    {
        $request = Request::fromParts('GET', '/', ['a' => 1, 'b' => 2]);
        $this->assertSame(['a' => 1, 'b' => 2], $request->queryParams());
    }

    public function testBodyParsedDirectly(): void
    {
        $request = Request::fromParts('POST', '/', [], ['key' => 'val']);
        $this->assertSame('val', $request->body('key'));
        $this->assertSame(['key' => 'val'], $request->bodyData());
    }

    public function testBodyDefault(): void
    {
        $request = Request::fromParts('GET', '/');
        $this->assertNull($request->body('missing'));
        $this->assertSame([], $request->bodyData());
    }

    public function testHeaderLookupCaseInsensitive(): void
    {
        $request = Request::fromParts('GET', '/', [], null, ['Content-Type' => 'application/json']);
        $this->assertSame('application/json', $request->header('Content-Type'));
        $this->assertSame('application/json', $request->header('content-type'));
        $this->assertSame('application/json', $request->header('CONTENT-TYPE'));
    }

    public function testHeaderDefault(): void
    {
        $request = Request::fromParts('GET', '/');
        $this->assertNull($request->header('X-Missing'));
        $this->assertSame('fallback', $request->header('X-Missing', 'fallback'));
    }

    public function testWithAttribute(): void
    {
        $request = Request::fromParts('GET', '/users/42');
        $modified = $request->withAttribute('id', '42');
        $this->assertSame('42', $modified->getAttribute('id'));
        // Original is unchanged
        $this->assertNull($request->getAttribute('id'));
    }

    public function testWithAttributes(): void
    {
        $request = Request::fromParts('GET', '/orgs/a/repos/b');
        $modified = $request->withAttributes(['org' => 'a', 'repo' => 'b']);
        $this->assertSame('a', $modified->getAttribute('org'));
        $this->assertSame('b', $modified->getAttribute('repo'));
    }

    public function testMethodUppercased(): void
    {
        $request = Request::fromParts('get', '/');
        $this->assertSame('GET', $request->method());
    }

    public function testRawBody(): void
    {
        $request = Request::fromParts('POST', '/', [], null, ['Content-Type' => 'application/json']);
        // When body not externally provided and running from CLI, rawBody will be null
        $this->assertSame([], $request->bodyData());
        // rawBody should be cached after bodyData() call
        $raw = $request->rawBody();
        $this->assertTrue($raw === null || $raw === '' || $raw === false);
    }
}
