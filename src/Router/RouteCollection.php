<?php

namespace Fishyboat21\SimpleApi\Router;

use Fishyboat21\SimpleApi\Exception\MethodNotAllowedException;
use Fishyboat21\SimpleApi\Exception\NotFoundException;

/**
 * Trie-based route matcher.
 *
 * Loads a pre-built route cache (nested array) and matches incoming
 * HTTP method + URI path to a handler class + method in O(depth) time.
 *
 * Static path segments are exact array keys (checked first, fast path).
 * The '*' key captures a path parameter.
 *
 * No regex compilation, no linear scan over all routes.
 */
class RouteCollection
{
    private array $trie;
    private string $basePath;
    private int $basePathSegments;

    /**
     * @param array  $trie     Route trie loaded from the generated cache file.
     * @param string $basePath URL prefix to strip before matching (e.g. "api").
     */
    public function __construct(array $trie, string $basePath = 'api')
    {
        $this->trie = $trie;
        $this->basePath = trim($basePath, '/');
        $this->basePathSegments = $this->basePath !== '' ? 1 : 0;
    }

    /**
     * Match a request method and URI to a handler.
     *
     * Walks the trie once. Distinguishes 404 (path not found) from
     * 405 (path found, method not allowed) in a single traversal.
     *
     * @return RouteMatch
     * @throws NotFoundException         If no route matches the path.
     * @throws MethodNotAllowedException If the path exists but doesn't support this method.
     *                                   The exception carries the list of allowed methods.
     */
    public function match(string $method, string $uri): RouteMatch
    {
        $path = parse_url($uri, PHP_URL_PATH);
        if ($path === false) {
            throw new NotFoundException();
        }

        $segments = explode('/', trim($path, '/'));
        $segCount = count($segments);

        $node = $this->trie;
        $params = [];

        // Start after base path offset (avoids array_shift O(n) cost)
        $start = ($this->basePathSegments > 0 && $segCount > 0 && $segments[0] === $this->basePath) ? 1 : 0;

        for ($i = $start; $i < $segCount; $i++) {
            $segment = $segments[$i];
            if ($segment === '') {
                continue;
            }

            // Static match first — exact key lookup (fast path)
            if (array_key_exists($segment, $node)) {
                $node = $node[$segment];
            } elseif (array_key_exists('*', $node)) {
                // Parameter match — capture the value
                $params[$node['*']['__name']] = $segment;
                $node = $node['*'];
            } else {
                throw new NotFoundException();
            }
        }

        // Route exists — check if the HTTP method is supported
        $handlers = $node['__handlers'] ?? [];

        if (!array_key_exists($method, $handlers)) {
            throw new MethodNotAllowedException(
                allowedMethods: array_keys($handlers),
            );
        }

        [$class, $handlerMethod] = $handlers[$method];
        return new RouteMatch($class, $handlerMethod, $params);
    }

    /**
     * Get the allowed HTTP methods for a path.
     *
     * @return string[] Empty if the path doesn't exist.
     */
    public function allowedMethods(string $uri): array
    {
        $path = parse_url($uri, PHP_URL_PATH);
        if ($path === false) {
            return [];
        }

        $segments = explode('/', trim($path, '/'));
        $segCount = count($segments);

        $node = $this->trie;
        $start = ($this->basePathSegments > 0 && $segCount > 0 && $segments[0] === $this->basePath) ? 1 : 0;

        for ($i = $start; $i < $segCount; $i++) {
            $segment = $segments[$i];
            if ($segment === '') {
                continue;
            }
            if (array_key_exists($segment, $node)) {
                $node = $node[$segment];
            } elseif (array_key_exists('*', $node)) {
                $node = $node['*'];
            } else {
                return [];
            }
        }

        return array_keys($node['__handlers'] ?? []);
    }
}
