<?php

namespace Fishyboat21\SimpleApi\Tests;

use Fishyboat21\SimpleApi\Config\CorsConfig;
use Fishyboat21\SimpleApi\Endpoint;
use Fishyboat21\SimpleApi\Http\Request;
use Fishyboat21\SimpleApi\Http\Response;
use PHPUnit\Framework\TestCase;

class EndpointTest extends TestCase
{
    // ── Handler registration ──────────────────────────────────

    public function testGetRegistrationReturnsSelf(): void
    {
        $api = new Endpoint();
        $result = $api->get(fn() => null);
        $this->assertSame($api, $result);
    }

    public function testPostRegistrationReturnsSelf(): void
    {
        $api = new Endpoint();
        $result = $api->post(fn() => null);
        $this->assertSame($api, $result);
    }

    public function testPutRegistrationReturnsSelf(): void
    {
        $api = new Endpoint();
        $result = $api->put(fn() => null);
        $this->assertSame($api, $result);
    }

    public function testDeleteRegistrationReturnsSelf(): void
    {
        $api = new Endpoint();
        $result = $api->delete(fn() => null);
        $this->assertSame($api, $result);
    }

    public function testPatchRegistrationReturnsSelf(): void
    {
        $api = new Endpoint();
        $result = $api->patch(fn() => null);
        $this->assertSame($api, $result);
    }

    public function testChainedRegistration(): void
    {
        $api = new Endpoint();
        $result = $api
            ->get(fn() => null)
            ->post(fn() => null)
            ->put(fn() => null);

        $this->assertSame($api, $result);
    }

    // ── Handler execution ─────────────────────────────────────

    public function testHandlerReceivesRequest(): void
    {
        $api = new Endpoint();
        $captured = null;

        $api->get(function (Request $request) use (&$captured): Response {
            $captured = $request;
            return Response::ok();
        });

        // Simulate a GET request
        $this->simulateRequest('GET', '/api/health');
        $this->runAndCapture($api);

        $this->assertInstanceOf(Request::class, $captured);
        $this->assertSame('GET', $captured->method());
    }

    public function testGetHandlerProducesOkResponse(): void
    {
        $api = new Endpoint();
        $api->get(fn(Request $r) => Response::ok(['status' => 'healthy']));

        $this->simulateRequest('GET', '/api/health');
        $output = $this->runAndCapture($api);

        $decoded = json_decode($output, true);
        $this->assertSame(200, $decoded['status']);
        $this->assertSame('OK', $decoded['message']);
        $this->assertSame(['status' => 'healthy'], $decoded['data']);
    }

    public function testPostHandlerProducesCreatedResponse(): void
    {
        $api = new Endpoint();
        $api->post(fn(Request $r) => Response::created(['id' => 1]));

        $this->simulateRequest('POST', '/api/users');
        $output = $this->runAndCapture($api);

        $decoded = json_decode($output, true);
        $this->assertSame(201, $decoded['status']);
        $this->assertSame('Created', $decoded['message']);
    }

    public function testNonResponseReturnIsWrapped(): void
    {
        $api = new Endpoint();
        $api->get(fn(Request $r) => ['key' => 'value']);

        $this->simulateRequest('GET', '/api/test');
        $output = $this->runAndCapture($api);

        $decoded = json_decode($output, true);
        $this->assertSame(200, $decoded['status']);
        $this->assertSame(['key' => 'value'], $decoded['data']);
    }

    // ── Method not allowed ────────────────────────────────────

    public function testMethodNotAllowedReturns405(): void
    {
        $api = new Endpoint();
        $api->get(fn(Request $r) => Response::ok());

        $this->simulateRequest('POST', '/api/health');
        $output = $this->runAndCapture($api);

        $decoded = json_decode($output, true);
        $this->assertSame(405, $decoded['status']);
        $this->assertSame('Method Not Allowed', $decoded['message']);
    }

    public function testMethodNotAllowedHasCorrectStatus(): void
    {
        $api = new Endpoint();
        $api->get(fn(Request $r) => Response::ok());
        $api->post(fn(Request $r) => Response::ok());

        $this->simulateRequest('DELETE', '/api/health');
        $output = $this->runAndCapture($api);

        $decoded = json_decode($output, true);
        $this->assertSame(405, $decoded['status']);
        // The Allow header is set via MethodNotAllowedException — tested
        // at the exception level in exception-specific tests
    }

    // ── CORS preflight ────────────────────────────────────────

    public function testOptionsPreflightReturnsNoContent(): void
    {
        $api = new Endpoint();

        $this->simulateRequest('OPTIONS', '/api/health', [
            'HTTP_ORIGIN' => 'http://localhost',
        ]);
        $output = $this->runAndCapture($api);

        $decoded = json_decode($output, true);
        $this->assertSame(204, $decoded['status']);
        $this->assertSame('No Content', $decoded['message']);
    }

    public function testOptionsPreflightHasNoContentStatus(): void
    {
        $api = new Endpoint();

        $this->simulateRequest('OPTIONS', '/api/health', [
            'HTTP_ORIGIN' => 'http://localhost',
        ]);
        $output = $this->runAndCapture($api);

        $decoded = json_decode($output, true);
        $this->assertSame(204, $decoded['status']);
        // CORS headers are set by the Cors class — tested at the Cors level
    }

    // ── Error handling ────────────────────────────────────────

    public function testUnhandledExceptionReturns500(): void
    {
        $api = new Endpoint();
        $api->get(function (Request $r): void {
            throw new \RuntimeException('Something broke');
        });

        $this->simulateRequest('GET', '/api/test');
        $output = $this->runAndCapture($api);

        $decoded = json_decode($output, true);
        $this->assertSame(500, $decoded['status']);
        // Real message is hidden from client
        $this->assertSame('Internal Server Error', $decoded['message']);
    }

    // ── CORS configuration ────────────────────────────────────

    public function testCustomCorsConfigDoesNotThrow(): void
    {
        $corsConfig = new CorsConfig(
            allowedOrigins: ['https://myapp.com'],
            allowedMethods: ['GET', 'POST'],
        );
        $api = new Endpoint($corsConfig);
        $api->get(fn(Request $r) => Response::ok());

        $this->simulateRequest('GET', '/api/test', [
            'HTTP_ORIGIN' => 'https://myapp.com',
        ]);
        $output = $this->runAndCapture($api);

        $decoded = json_decode($output, true);
        $this->assertSame(200, $decoded['status']);
        // Specific origin CORS headers applied by Cors — tested at Cors level
    }

    // ── Logger ────────────────────────────────────────────────

    public function testSetLoggerReturnsSelf(): void
    {
        $api = new Endpoint();
        $logger = new class {
            public function error(string $message, array $context = []): void {}
        };
        $result = $api->setLogger($logger);
        $this->assertSame($api, $result);
    }

    // ── Default constructor ───────────────────────────────────

    public function testConstructorAcceptsNoArguments(): void
    {
        $api = new Endpoint();
        $this->assertInstanceOf(Endpoint::class, $api);
    }

    // ── Helpers ───────────────────────────────────────────────

    /**
     * Set up $_SERVER superglobals to simulate a request.
     */
    private function simulateRequest(string $method, string $uri, array $extra = []): void
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $_GET = [];
        parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $_GET);

        foreach ($extra as $key => $value) {
            $_SERVER[$key] = $value;
        }
    }

    /**
     * Run the endpoint and capture all output.
     */
    private function runAndCapture(Endpoint $api): string
    {
        ob_start();
        $api->run();
        return ob_get_clean() ?: '';
    }

    protected function tearDown(): void
    {
        // Reset superglobals
        unset($_SERVER['REQUEST_METHOD']);
        unset($_SERVER['REQUEST_URI']);
        unset($_SERVER['HTTP_ORIGIN']);
        $_GET = [];
    }
}
