<?php

namespace Fishyboat21\SimpleApi\Tests;

use Fishyboat21\SimpleApi\Exception\MethodNotAllowedException;
use Fishyboat21\SimpleApi\Exception\NotFoundException;
use Fishyboat21\SimpleApi\Router\RouteCollection;
use PHPUnit\Framework\TestCase;

class RouteCollectionTest extends TestCase
{
    private array $trie;

    protected function setUp(): void
    {
        $this->trie = [
            'health' => [
                '__handlers' => [
                    'GET' => ['App\Api\Health\Index', 'handle'],
                ],
            ],
            'users' => [
                '__handlers' => [
                    'GET'  => ['App\Api\Users\Index', 'list'],
                    'POST' => ['App\Api\Users\Index', 'create'],
                ],
                '*' => [
                    '__name' => 'id',
                    '__handlers' => [
                        'GET'    => ['App\Api\Users\_id\Index', 'show'],
                        'PUT'    => ['App\Api\Users\_id\Index', 'update'],
                        'DELETE' => ['App\Api\Users\_id\Index', 'delete'],
                    ],
                ],
            ],
        ];
    }

    public function testStaticRouteMatch(): void
    {
        $router = new RouteCollection($this->trie, 'api');
        $match = $router->match('GET', '/api/health');
        $this->assertSame('App\Api\Health\Index', $match->handlerClass);
        $this->assertSame('handle', $match->handlerMethod);
        $this->assertSame([], $match->params);
    }

    public function testStaticRouteWithoutBasePath(): void
    {
        $router = new RouteCollection($this->trie, '');
        $match = $router->match('GET', '/health');
        $this->assertSame('App\Api\Health\Index', $match->handlerClass);
    }

    public function testParameterizedRoute(): void
    {
        $router = new RouteCollection($this->trie, 'api');
        $match = $router->match('GET', '/api/users/42');
        $this->assertSame('App\Api\Users\_id\Index', $match->handlerClass);
        $this->assertSame('show', $match->handlerMethod);
        $this->assertSame(['id' => '42'], $match->params);
    }

    public function testStaticRouteMethodNotAllowed(): void
    {
        $router = new RouteCollection($this->trie, 'api');
        $this->expectException(MethodNotAllowedException::class);
        $router->match('DELETE', '/api/health');
    }

    public function testMethodNotAllowedContainsAllowedMethods(): void
    {
        $router = new RouteCollection($this->trie, 'api');
        try {
            $router->match('DELETE', '/api/health');
            $this->fail('Expected MethodNotAllowedException');
        } catch (MethodNotAllowedException $e) {
            $this->assertContains('GET', $e->allowedMethods);
            $this->assertSame(405, $e->getStatusCode());
        }
    }

    public function testRouteNotFound(): void
    {
        $router = new RouteCollection($this->trie, 'api');
        $this->expectException(NotFoundException::class);
        $router->match('GET', '/api/nonexistent');
    }

    public function testParameterizedRouteMethodNotAllowed(): void
    {
        $router = new RouteCollection($this->trie, 'api');
        $this->expectException(MethodNotAllowedException::class);
        $router->match('PATCH', '/api/users/42');
    }

    public function testRouteNotFoundDeepPath(): void
    {
        $router = new RouteCollection($this->trie, 'api');
        $this->expectException(NotFoundException::class);
        $router->match('GET', '/api/users/42/posts/99');
    }

    public function testStaticMatchPreferredOverParameter(): void
    {
        // When a static segment exists at the same level as a '*', static should win
        $router = new RouteCollection($this->trie, 'api');
        $match = $router->match('GET', '/api/users');
        $this->assertSame('list', $match->handlerMethod);
        $this->assertSame([], $match->params);
    }

    public function testPostOnUsersCollection(): void
    {
        $router = new RouteCollection($this->trie, 'api');
        $match = $router->match('POST', '/api/users');
        $this->assertSame('create', $match->handlerMethod);
    }

    public function testAllowedMethods(): void
    {
        $router = new RouteCollection($this->trie, 'api');
        $this->assertSame(['GET'], $router->allowedMethods('/api/health'));
        $this->assertSame(['GET', 'POST'], $router->allowedMethods('/api/users'));
    }
}
