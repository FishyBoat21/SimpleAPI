<?php

namespace Fishyboat21\SimpleApi\Router;

/**
 * Result of a successful route match.
 */
readonly class RouteMatch
{
    public function __construct(
        public string $handlerClass,
        public string $handlerMethod,
        public array $params = [],
    ) {}
}
