<?php

namespace Fishyboat21\SimpleApi\Config;

/**
 * Immutable CORS configuration value object.
 */
readonly class CorsConfig
{
    /**
     * @param string[] $allowedOrigins   Allowed origins ("*" for any).
     * @param string[] $allowedMethods   Allowed HTTP methods.
     * @param string[] $allowedHeaders   Allowed request headers.
     * @param bool     $allowCredentials Whether to allow credentials (cookies, auth).
     * @param int      $maxAge           Preflight cache duration in seconds.
     */
    public function __construct(
        public array $allowedOrigins = ['*'],
        public array $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
        public array $allowedHeaders = ['Content-Type', 'Authorization', 'X-Requested-With'],
        public bool $allowCredentials = false,
        public int $maxAge = 86400,
    ) {}
}
