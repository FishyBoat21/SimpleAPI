<?php

namespace Fishyboat21\SimpleApi\Http;

/**
 * Lightweight request value object wrapping superglobals.
 *
 * Body (php://input) is parsed lazily on first access.
 * Path parameters from route matching are set via withAttributes().
 */
class Request
{
    private string $method;
    private string $uri;
    private string $path;
    private array $query;
    private ?array $body = null;
    private ?string $rawBody = null;
    private bool $bodyParsed = false;
    private array $attributes;
    /** @var array<string, string> Lowercase header name → value */
    private array $headers;

    public function __construct(
        string $method,
        string $uri,
        array $query = [],
        ?array $body = null,
        array $headers = [],
        array $attributes = [],
    ) {
        $this->method = strtoupper($method);
        $this->uri = $uri;
        $this->path = parse_url($uri, PHP_URL_PATH) ?? '/';
        $this->query = $query;
        $this->body = $body;
        $this->bodyParsed = $body !== null;
        $this->attributes = $attributes;

        // Normalize header keys to lowercase for O(1) lookup
        $normalizedHeaders = [];
        foreach ($headers as $name => $value) {
            $normalizedHeaders[strtolower($name)] = $value;
        }
        $this->headers = $normalizedHeaders;
    }

    /** Create a request from PHP superglobals. */
    public static function fromGlobals(): self
    {
        // Prefer getallheaders() when available (C-level, no $_SERVER iteration)
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            $headers = [];
            foreach ($_SERVER as $key => $value) {
                if (str_starts_with($key, 'HTTP_')) {
                    $name = str_replace('_', '-', substr($key, 5));
                    $headers[$name] = $value;
                }
            }
            if (isset($_SERVER['CONTENT_TYPE'])) {
                $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
            }
            if (isset($_SERVER['CONTENT_LENGTH'])) {
                $headers['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
            }
        }

        return new self(
            method: $_SERVER['REQUEST_METHOD'] ?? 'GET',
            uri: $_SERVER['REQUEST_URI'] ?? '/',
            query: $_GET,
            body: null, // lazy
            headers: $headers,
        );
    }

    /** Create a request from explicit parts (useful for testing). */
    public static function fromParts(
        string $method,
        string $uri,
        array $query = [],
        ?array $body = null,
        array $headers = [],
    ): self {
        return new self($method, $uri, $query, $body, $headers);
    }

    // ── Accessors ────────────────────────────────────────────

    public function method(): string
    {
        return $this->method;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    /** Get the URL path without query string. Cached after first call. */
    public function path(): string
    {
        return $this->path;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function queryParams(): array
    {
        return $this->query;
    }

    /** Get a single body field. Parses body lazily on first call. */
    public function body(string $key, mixed $default = null): mixed
    {
        return $this->bodyData()[$key] ?? $default;
    }

    /** Get all parsed body data. Parses body lazily on first call. */
    public function bodyData(): array
    {
        if (!$this->bodyParsed) {
            $this->rawBody = file_get_contents('php://input');
            if ($this->rawBody === false || $this->rawBody === '') {
                $this->body = [];
            } else {
                $decoded = json_decode($this->rawBody, true);
                $this->body = is_array($decoded) ? $decoded : [];
            }
            $this->bodyParsed = true;
        }
        return $this->body;
    }

    /** Get the raw (unparsed) request body. Parses body lazily on first call. */
    public function rawBody(): ?string
    {
        if (!$this->bodyParsed) {
            $this->bodyData(); // triggers parse and caches raw body
        }
        return $this->rawBody;
    }

    /** O(1) case-insensitive header lookup using normalized lowercase keys. */
    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    // ── Path parameters ──────────────────────────────────────

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /** Return a new Request with multiple additional attributes (single clone). */
    public function withAttributes(array $attributes): self
    {
        $clone = clone $this;
        foreach ($attributes as $key => $value) {
            $clone->attributes[$key] = $value;
        }
        return $clone;
    }

    /** Return a new Request with a single additional attribute. */
    public function withAttribute(string $key, mixed $value): self
    {
        return $this->withAttributes([$key => $value]);
    }

    public function attributes(): array
    {
        return $this->attributes;
    }
}
