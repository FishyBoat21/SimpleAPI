<?php

namespace Fishyboat21\SimpleApi;

use Fishyboat21\SimpleApi\Config\CorsConfig;
use Fishyboat21\SimpleApi\Exception\HttpException;
use Fishyboat21\SimpleApi\Exception\MethodNotAllowedException;
use Fishyboat21\SimpleApi\Exception\NotFoundException;
use Fishyboat21\SimpleApi\Http\Request;
use Fishyboat21\SimpleApi\Http\Response;
use Fishyboat21\SimpleApi\Interface\ApiHandler;
use Fishyboat21\SimpleApi\Router\RouteCollection;
use Throwable;

/**
 * Main application entry point.
 *
 * Loads a pre-built route cache, handles CORS, matches incoming requests
 * to handler classes, dispatches them, and sends JSON responses.
 *
 * Usage:
 *   $api = new SimpleApi(routeCacheFile: __DIR__ . '/../storage/routes.php');
 *   $api->run();
 */
class SimpleApi
{
    private RouteCollection $router;
    private Cors $cors;
    private ?object $logger = null;
    private ?string $loggerMethod = null;

    /**
     * @param string         $routeCacheFile Path to the generated route cache PHP file.
     * @param CorsConfig|null $corsConfig    CORS configuration (defaults to allow all origins).
     * @param string         $baseUrlPath    URL prefix for routes (e.g. "api").
     */
    public function __construct(
        string $routeCacheFile,
        ?CorsConfig $corsConfig = null,
        string $baseUrlPath = 'api',
    ) {
        if (!file_exists($routeCacheFile)) {
            throw new \RuntimeException(
                "Route cache file not found: {$routeCacheFile}. " .
                "Run 'vendor/bin/simple-api route:generate' first."
            );
        }

        /** @var array $trie */
        $trie = require $routeCacheFile;
        $this->router = new RouteCollection($trie, $baseUrlPath);
        $this->cors = new Cors($corsConfig ?? new CorsConfig());
    }

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

            // 2. Route matching (single trie walk; throws on 404/405)
            $match = $this->router->match($request->method(), $request->uri());

            // 3. Set path parameters (single clone)
            if ($match->params !== []) {
                $request = $request->withAttributes($match->params);
            }

            // 4. Instantiate handler (PSR-4 autoloader fires here — lazy load)
            $handlerClass = $match->handlerClass;

            // Security: verify the loaded class implements ApiHandler
            if (!is_subclass_of($handlerClass, ApiHandler::class, true)) {
                throw new \RuntimeException("Handler class {$handlerClass} does not implement ApiHandler");
            }

            $handler = new $handlerClass();
            $handlerMethod = $match->handlerMethod;

            // 5. Dispatch to handler
            $response = $handler->$handlerMethod($request);

            // Wrap non-Response return values
            if (!($response instanceof Response)) {
                $response = Response::ok($response);
            }

            // 6. Apply CORS headers
            $response = $this->cors->applyToResponse($request, $response);

            // 7. Send
            $this->send($response);

        } catch (NotFoundException $e) {
            $this->send(Response::notFound($e->getMessage()));
        } catch (MethodNotAllowedException $e) {
            $response = Response::error(405, $e->getMessage())
                ->setHeader('Allow', $e->getAllowHeader());
            $this->send($response);
        } catch (HttpException $e) {
            $this->send(Response::error($e->getStatusCode(), $e->getMessage()));
        } catch (Throwable $e) {
            $this->logError($e);
            $this->send(Response::error(500, 'Internal Server Error'));
        }
    }

    // ── Response sending ─────────────────────────────────────

    /**
     * Send a JSON response.
     */
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

    // ── Error logging ────────────────────────────────────────

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
