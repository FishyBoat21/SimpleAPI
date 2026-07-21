<?php

namespace Fishyboat21\SimpleApi;

use Fishyboat21\SimpleApi\Config\CorsConfig;
use Fishyboat21\SimpleApi\Exception\HttpException;
use Fishyboat21\SimpleApi\Exception\MethodNotAllowedException;
use Fishyboat21\SimpleApi\Http\Request;
use Fishyboat21\SimpleApi\Http\Response;
use Throwable;

/**
 * Lightweight per-endpoint helper.
 *
 * Each public/api/*.php file creates an Endpoint instance, registers
 * handlers for HTTP methods, and calls run(). The Endpoint handles
 * CORS, request parsing, dispatch, error handling, and JSON output.
 *
 * Usage:
 *   $api = new Endpoint();
 *   $api->get(fn(Request $r) => Response::ok(['data' => ...]));
 *   $api->post(fn(Request $r) => Response::created(['id' => 1]));
 *   $api->run();
 */
class Endpoint
{
    /** @var array<string, callable> Lowercase HTTP method → handler */
    private array $handlers = [];

    private readonly Cors $cors;

    private ?object $logger = null;
    private ?string $loggerMethod = null;

    private const KNOWN_METHODS = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'];

    public function __construct(?CorsConfig $corsConfig = null)
    {
        $this->cors = new Cors($corsConfig ?? new CorsConfig());
    }

    // ── Handler registration ──────────────────────────────────

    public function get(callable $handler): self
    {
        $this->handlers['get'] = $handler;
        return $this;
    }

    public function post(callable $handler): self
    {
        $this->handlers['post'] = $handler;
        return $this;
    }

    public function put(callable $handler): self
    {
        $this->handlers['put'] = $handler;
        return $this;
    }

    public function delete(callable $handler): self
    {
        $this->handlers['delete'] = $handler;
        return $this;
    }

    public function patch(callable $handler): self
    {
        $this->handlers['patch'] = $handler;
        return $this;
    }

    // ── Logger ─────────────────────────────────────────────────

    /**
     * Attach a PSR-3 compatible logger for error reporting.
     *
     * Accepts any object with an `error(string $message, array $context): void` method.
     */
    public function setLogger(object $logger): self
    {
        if (method_exists($logger, 'error')) {
            $this->logger = $logger;
            $this->loggerMethod = 'error';
        }
        return $this;
    }

    // ── Run ────────────────────────────────────────────────────

    /**
     * Process the incoming request and send the response.
     */
    public function run(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $request = Request::fromGlobals();

        try {
            // 1. CORS preflight
            $preflightResponse = $this->cors->handlePreflight($request);
            if ($preflightResponse !== null) {
                $this->send($preflightResponse);
                return;
            }

            // 2. Find handler for HTTP method (stored lowercase)
            $methodKey = strtolower($request->method());

            if (!isset($this->handlers[$methodKey])) {
                throw new MethodNotAllowedException(
                    allowedMethods: $this->allowedMethods(),
                );
            }

            // 3. Dispatch
            $response = ($this->handlers[$methodKey])($request);

            // Wrap non-Response return values
            if (!($response instanceof Response)) {
                $response = Response::ok($response);
            }

            // 4. Apply CORS headers
            $response = $this->cors->applyToResponse($request, $response);

            // 5. Send
            $this->send($response);

        } catch (MethodNotAllowedException $e) {
            $response = Response::error(405, $e->getMessage())
                ->setHeader('Allow', $e->getAllowHeader());
            $response = $this->cors->applyToResponse($request, $response);
            $this->send($response);
        } catch (HttpException $e) {
            $response = Response::error($e->getStatusCode(), $e->getMessage());
            $response = $this->cors->applyToResponse($request, $response);
            $this->send($response);
        } catch (Throwable $e) {
            $this->logError($e);
            $response = Response::error(500, 'Internal Server Error');
            $response = $this->cors->applyToResponse($request, $response);
            $this->send($response);
        }
    }

    // ── Internal ───────────────────────────────────────────────

    /**
     * Get the list of HTTP methods this endpoint responds to.
     *
     * @return string[]
     */
    private function allowedMethods(): array
    {
        $allowed = [];
        foreach (self::KNOWN_METHODS as $method) {
            if (isset($this->handlers[strtolower($method)])) {
                $allowed[] = $method;
            }
        }
        return $allowed;
    }

    private function send(Response $response): void
    {
        http_response_code($response->status);

        foreach ($response->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        $json = json_encode(
            $response->toArray(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            http_response_code(500);
            $json = '{"status":500,"message":"Internal Server Error"}';
        }

        echo $json;
    }

    private function logError(Throwable $e): void
    {
        if ($this->logger === null || $this->loggerMethod === null) {
            return;
        }

        try {
            ($this->logger)->{$this->loggerMethod}($e->getMessage(), [
                'exception' => $e,
                'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            ]);
        } catch (Throwable) {
            // Swallow logging errors
        }
    }
}
