<?php

namespace Fishyboat21\SimpleApi;

use Fishyboat21\SimpleApi\Config\CorsConfig;
use Fishyboat21\SimpleApi\Http\Request;
use Fishyboat21\SimpleApi\Http\Response;

/**
 * Handles CORS preflight requests and response headers.
 */
class Cors
{
    private readonly bool $allowAllOrigins;
    private readonly string $allowedMethodsHeader;
    private readonly string $allowedHeadersHeader;
    private readonly string $maxAgeHeader;

    public function __construct(
        private readonly CorsConfig $config,
    ) {
        $this->allowAllOrigins = in_array('*', $this->config->allowedOrigins, true);
        $this->allowedMethodsHeader = implode(', ', $this->config->allowedMethods);
        $this->allowedHeadersHeader = implode(', ', $this->config->allowedHeaders);
        $this->maxAgeHeader = (string)$this->config->maxAge;

        // CORS spec: wildcard origin is incompatible with credentials
        if ($this->allowAllOrigins && $this->config->allowCredentials) {
            throw new \RuntimeException(
                'CORS configuration error: Cannot use wildcard origin (*) ' .
                'with credentials enabled. Specify explicit origins when using credentials.'
            );
        }
    }

    /**
     * Handle a CORS preflight request.
     *
     * @return Response|null A preflight response if this is an OPTIONS
     *                       request with a valid Origin header, or null.
     */
    public function handlePreflight(Request $request): ?Response
    {
        if ($request->method() !== 'OPTIONS') {
            return null;
        }

        $origin = $request->header('Origin');
        if ($origin === null || !$this->isOriginAllowed($origin)) {
            return null;
        }

        $response = Response::noContent()
            ->setHeader('Access-Control-Allow-Origin', $this->resolveOrigin($origin))
            ->setHeader('Access-Control-Allow-Methods', $this->allowedMethodsHeader)
            ->setHeader('Access-Control-Allow-Headers', $this->allowedHeadersHeader)
            ->setHeader('Access-Control-Max-Age', $this->maxAgeHeader)
            ->setHeader('Vary', 'Origin');

        if ($this->config->allowCredentials) {
            $response = $response->setHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    /**
     * Apply CORS headers to an actual (non-preflight) response.
     */
    public function applyToResponse(Request $request, Response $response): Response
    {
        $origin = $request->header('Origin');
        if ($origin === null || !$this->isOriginAllowed($origin)) {
            return $response;
        }

        $response = $response->setHeader('Access-Control-Allow-Origin', $this->resolveOrigin($origin));
        $response = $response->setHeader('Vary', 'Origin');

        if ($this->config->allowCredentials) {
            $response = $response->setHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    private function isOriginAllowed(string $origin): bool
    {
        if ($this->allowAllOrigins) {
            return true;
        }
        return in_array($origin, $this->config->allowedOrigins, true);
    }

    /**
     * Resolve the value for Access-Control-Allow-Origin.
     * "*" config → literal "*". Specific origin → echo it back.
     */
    private function resolveOrigin(string $origin): string
    {
        return $this->allowAllOrigins ? '*' : $origin;
    }
}
