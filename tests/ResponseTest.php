<?php

namespace Fishyboat21\SimpleApi\Tests;

use Fishyboat21\SimpleApi\Http\Response;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    public function testOkResponse(): void
    {
        $response = Response::ok(['key' => 'value']);
        $this->assertSame(200, $response->status);
        $this->assertSame('OK', $response->message);
        $this->assertSame(['key' => 'value'], $response->data);
    }

    public function testOkWithDefaults(): void
    {
        $response = Response::ok();
        $this->assertSame(200, $response->status);
        $this->assertSame('OK', $response->message);
        $this->assertNull($response->data);
    }

    public function testCreated(): void
    {
        $response = Response::created(['id' => 1]);
        $this->assertSame(201, $response->status);
        $this->assertSame('Created', $response->message);
    }

    public function testNoContent(): void
    {
        $response = Response::noContent();
        $this->assertSame(204, $response->status);
        $this->assertSame('No Content', $response->message);
    }

    public function testNotFound(): void
    {
        $response = Response::notFound('Resource not found');
        $this->assertSame(404, $response->status);
        $this->assertSame('Resource not found', $response->message);
    }

    public function testError(): void
    {
        $response = Response::error(500, 'Internal Server Error');
        $this->assertSame(500, $response->status);
        $this->assertSame('Internal Server Error', $response->message);
    }

    public function testToArrayOmitsDataWhenNull(): void
    {
        $response = Response::ok();
        $arr = $response->toArray();
        $this->assertArrayHasKey('status', $arr);
        $this->assertArrayHasKey('message', $arr);
        $this->assertArrayNotHasKey('data', $arr);
    }

    public function testToArrayIncludesDataWhenSet(): void
    {
        $response = Response::ok(['x' => 1]);
        $arr = $response->toArray();
        $this->assertArrayHasKey('data', $arr);
        $this->assertSame(['x' => 1], $arr['data']);
    }

    public function testSetHeader(): void
    {
        $response = Response::ok();
        $response->setHeader('X-Custom', 'value');
        $this->assertArrayHasKey('X-Custom', $response->headers);
        $this->assertSame('value', $response->headers['X-Custom']);
    }

    public function testSetHeaderStripsCrLf(): void
    {
        $response = Response::ok();
        $response->setHeader("X-Injected\r\n", "evil\r\nvalue");
        $this->assertArrayNotHasKey("X-Injected\r\n", $response->headers);
        $this->assertStringNotContainsString("\r", $response->headers['X-Injected'] ?? '');
        $this->assertStringNotContainsString("\n", $response->headers['X-Injected'] ?? '');
    }

    public function testRemoveHeader(): void
    {
        $response = Response::ok()->setHeader('X-Remove', 'gone');
        $response->removeHeader('X-Remove');
        $this->assertArrayNotHasKey('X-Remove', $response->headers);
    }

    public function testSetHeaderReturnsSelf(): void
    {
        $response = Response::ok();
        $result = $response->setHeader('X', 'y');
        $this->assertSame($response, $result);
    }

    public function testConstructorDefaults(): void
    {
        $response = new Response();
        $this->assertSame(200, $response->status);
        $this->assertSame('', $response->message);
        $this->assertNull($response->data);
        $this->assertSame([], $response->headers);
    }
}
