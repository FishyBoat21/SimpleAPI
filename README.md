# SimpleAPI

A minimal, zero-dependency PHP 8.4+ library for building JSON HTTP APIs using a **file-per-endpoint pattern**. Each endpoint is a single PHP file in `public/api/` — no complex routing, no route cache, no code generation. Just create a file and it's live.

## Requirements

- PHP >= 8.4
- Composer

## Installation

```bash
composer require fishyboat21/simple-api
```

## Quick Start

### 1. Create your first endpoint

**`public/api/health.php`**

```php
<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Fishyboat21\SimpleApi\Endpoint;
use Fishyboat21\SimpleApi\Http\Request;
use Fishyboat21\SimpleApi\Http\Response;

$api = new Endpoint();

$api->get(function (Request $request): Response {
    return Response::ok(['status' => 'healthy']);
});

$api->run();
```

### 2. Start the server

```bash
php -S localhost:8000 -t public/
```

### 3. Test it

```bash
curl http://localhost:8000/api/health.php
# → {"status":200,"message":"OK","data":{"status":"healthy"}}
```

---

## Clean URLs

Enable clean URLs without the `.php` extension by placing this `.htaccess` in your `public/` directory:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^api/(.+)$ api/$1.php [QSA,L]
</IfModule>
```

With the rewrite rule:
```bash
curl http://localhost:8000/api/health
# → {"status":200,"message":"OK","data":{"status":"healthy"}}
```

---

## Creating Endpoints

### Single-method endpoint

```php
<?php
// public/api/products.php
require_once __DIR__ . '/../../vendor/autoload.php';

use Fishyboat21\SimpleApi\Endpoint;
use Fishyboat21\SimpleApi\Http\Request;
use Fishyboat21\SimpleApi\Http\Response;

$api = new Endpoint();

$api->get(function (Request $request): Response {
    return Response::ok([
        'products' => [/* ... */],
    ]);
});

$api->run();
```

### Multi-method endpoint

Register multiple HTTP method handlers on the same file:

```php
<?php
// public/api/users.php
require_once __DIR__ . '/../../vendor/autoload.php';

use Fishyboat21\SimpleApi\Endpoint;
use Fishyboat21\SimpleApi\Http\Request;
use Fishyboat21\SimpleApi\Http\Response;

$api = new Endpoint();

$api->get(function (Request $request): Response {
    return Response::ok(['users' => [/* all users */]]);
});

$api->post(function (Request $request): Response {
    $name = $request->body('name');
    // ... create user ...
    return Response::created(['id' => 1], 'User created');
});

$api->run();
```

Supported methods: `get()`, `post()`, `put()`, `delete()`, `patch()`.

---

## Request Handling

The `Request` object provides access to every part of the incoming request:

```php
$api->get(function (Request $request): Response {
    // Query string: /api/search.php?q=hello&page=2
    $query  = $request->query('q');          // 'hello'
    $page   = $request->query('page', '1');  // '2' (with default)
    $all    = $request->queryParams();       // ['q' => 'hello', 'page' => '2']

    // JSON body (parsed lazily on first access)
    $name   = $request->body('name');        // single field
    $all    = $request->bodyData();          // entire parsed body as array
    $raw    = $request->rawBody();           // raw unparsed body string

    // Headers (case-insensitive)
    $auth   = $request->header('Authorization');
    $type   = $request->header('Content-Type');

    // Request metadata
    $method = $request->method();            // 'GET', 'POST', etc.
    $uri    = $request->uri();               // '/api/search.php?q=hello'
    $path   = $request->path();              // '/api/search.php'

    return Response::ok(/* ... */);
});
```

---

## Response Helpers

```php
// 200 OK with data
Response::ok(['user' => $user]);

// 200 OK with custom message
Response::ok(['user' => $user], 'User retrieved');

// 201 Created
Response::created(['id' => 42], 'User created');

// 204 No Content (no body)
Response::noContent();

// 404 Not Found
Response::notFound('User not found');

// Custom error status
Response::error(422, 'Validation failed');

// Custom response with headers
Response::ok(['data' => $value])
    ->setHeader('X-Custom', 'value')
    ->setHeader('Cache-Control', 'no-cache');
```

### JSON response format

```json
// Success (data present)
{"status":200,"message":"OK","data":{...}}

// Success (data null — key omitted)
{"status":200,"message":"OK"}

// Error
{"status":404,"message":"Not Found"}
```

---

## CORS Configuration

CORS is configurable per-endpoint or globally with safe defaults (allow all origins, common methods).

```php
<?php
// public/api/health.php
require_once __DIR__ . '/../../vendor/autoload.php';

use Fishyboat21\SimpleApi\Config\CorsConfig;
use Fishyboat21\SimpleApi\Endpoint;
use Fishyboat21\SimpleApi\Http\Request;
use Fishyboat21\SimpleApi\Http\Response;

$cors = new CorsConfig(
    allowedOrigins: ['https://myapp.com', 'https://admin.myapp.com'],
    allowedMethods: ['GET', 'POST', 'PUT', 'DELETE'],
    allowedHeaders: ['Content-Type', 'Authorization'],
    allowCredentials: true,
    maxAge: 3600,
);

$api = new Endpoint(corsConfig: $cors);

$api->get(function (Request $request): Response {
    return Response::ok(['status' => 'healthy']);
});

$api->run();
```

> **Note:** Using `allowedOrigins: ['*']` together with `allowCredentials: true` will throw a `RuntimeException` — the CORS spec forbids this combination.

---

## Error Handling & Logging

### Error responses

| Condition | Status | Body |
|---|---|---|
| Method not allowed | 405 | `{"status":405,"message":"Method Not Allowed"}` + `Allow` header |
| Unhandled exception | 500 | `{"status":500,"message":"Internal Server Error"}` |
| Custom `HttpException` | *varies* | Status from exception, safe message exposed |

> **Security:** Unhandled exceptions return a generic `"Internal Server Error"` message. The real error is logged server-side, never exposed to the client.

### Attaching a logger

```php
$api = new Endpoint();
$api->setLogger($myPsr3Logger);  // any object with an error() method
```

### Custom HTTP exceptions in handlers

```php
use Fishyboat21\SimpleApi\Exception\HttpException;
use Fishyboat21\SimpleApi\Exception\NotFoundException;

$api->get(function (Request $request): Response {
    $user = $this->findUser($request->query('id'));
    if ($user === null) {
        throw new NotFoundException('User not found');
    }
    return Response::ok($user);
});
```

Create your own exception types:

```php
class UnprocessableEntityException extends HttpException
{
    public function __construct(string $message = 'Unprocessable Entity')
    {
        parent::__construct($message, 422);
    }
    public function getStatusCode(): int { return 422; }
}
```

---

## Complete Example

**`public/api/task.php`**

```php
<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Fishyboat21\SimpleApi\Endpoint;
use Fishyboat21\SimpleApi\Exception\NotFoundException;
use Fishyboat21\SimpleApi\Http\Request;
use Fishyboat21\SimpleApi\Http\Response;

$api = new Endpoint();

$api->get(function (Request $request): Response {
    $taskId = (int) $request->query('id');

    $task = findTask($taskId);  // your data access
    if ($task === null) {
        throw new NotFoundException("Task {$taskId} not found");
    }

    return Response::ok($task);
});

$api->put(function (Request $request): Response {
    $taskId = (int) $request->query('id');
    $title  = $request->body('title');
    $done   = $request->body('done', false);

    $task = updateTask($taskId, $title, $done);
    return Response::ok($task, 'Task updated');
});

$api->delete(function (Request $request): Response {
    $taskId = (int) $request->query('id');
    removeTask($taskId);
    return Response::noContent();
});

$api->run();
```

---

## Performance

- **No routing overhead** — each endpoint file is served directly by the web server
- **No route cache** — nothing to generate or load
- **Lazy request body parsing** — `php://input` is read only if the handler calls `body()` or `bodyData()`
- **No middleware stack** — fixed pipeline: CORS → dispatch → send

---

## License

MIT
