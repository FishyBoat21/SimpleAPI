<?php

namespace Fishyboat21\SimpleApi\Http;

/**
 * JSON API response value object with factory methods.
 *
 * Uses plain PHP 8.4 typed properties (no identity hooks needed).
 * setHeader() returns $this for fluent chaining.
 */
class Response
{
    public int $status;
    public string $message;
    public object|array|null $data;
    public array $headers;

    public function __construct(
        int $status = 200,
        string $message = '',
        object|array|null $data = null,
        array $headers = [],
    ) {
        $this->status = $status;
        $this->message = $message;
        $this->data = $data;
        $this->headers = $headers;
    }

    // ── Factory methods ──────────────────────────────────────

    public static function ok(mixed $data = null, string $message = 'OK'): self
    {
        return new self(status: 200, message: $message, data: $data);
    }

    public static function created(mixed $data = null, string $message = 'Created'): self
    {
        return new self(status: 201, message: $message, data: $data);
    }

    public static function noContent(): self
    {
        return new self(status: 204, message: 'No Content');
    }

    public static function notFound(string $message = 'Not Found'): self
    {
        return new self(status: 404, message: $message);
    }

    public static function error(int $status = 500, string $message = 'Internal Server Error'): self
    {
        return new self(status: $status, message: $message);
    }

    // ── Serialization ────────────────────────────────────────

    /** Convert to array for JSON encoding. Omits data key when null. */
    public function toArray(): array
    {
        $result = [
            'status' => $this->status,
            'message' => $this->message,
        ];
        if ($this->data !== null) {
            $result['data'] = $this->data;
        }
        return $result;
    }

    // ── Header helpers ───────────────────────────────────────

    /**
     * Set a response header. Returns $this for chaining.
     * Strips CR/LF to prevent HTTP header injection.
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[str_replace(["\r", "\n"], '', $name)]
            = str_replace(["\r", "\n"], '', $value);
        return $this;
    }

    /** Remove a response header. Returns $this for chaining. */
    public function removeHeader(string $name): self
    {
        unset($this->headers[$name]);
        return $this;
    }

    /**
     * @deprecated Use setHeader() instead.
     */
    public function withHeader(string $name, string $value): self
    {
        return $this->setHeader($name, $value);
    }
}
