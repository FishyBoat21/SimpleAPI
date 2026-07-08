# SimpleAPI

A minimal, zero-dependency PHP 8.4+ library for building JSON HTTP APIs using a **per-file endpoint pattern**. The filesystem *is* your routing table — no manual route registration, no YAML, no annotations.

## Requirements

- PHP >= 8.4
- Composer

## Installation

```bash
composer require fishyboat21/simple-api
```

## Quick Start

### 1. Create the directory structure

```
your-project/
├── public/
│   └── index.php          # Front controller
├── src/
│   └── Api/               # Your endpoint classes live here
├── storage/               # Generated route cache
├── vendor/
└── composer.json
```

Add the PSR-4 autoload mapping for your API namespace in `composer.json`:

```json
{
    "autoload": {
        "psr-4": {
            "App\\Api\\": "src/Api/"
        }
    },
    "scripts": {
        "route:generate": "simple-api route:generate"
    }
}
```

Run `composer dump-autoload` after adding the mapping.

### 2. Create your first endpoint

**`src/Api/Health/Index.php`**

```php
<?php

namespace App\Api\Health;

use Fishyboat21\SimpleApi\Attribute\Route;
use Fishyboat21\SimpleApi\Enum\Method;
use Fishyboat21\SimpleApi\Http\Request;
use Fishyboat21\SimpleApi\Http\Response;
use Fishyboat21\SimpleApi\Interface\ApiHandler;

#[Route(Method::GET)]
class Index implements ApiHandler
{
    public function handle(Request $request): Response
    {
        return Response::ok(['status' => 'healthy']);
    }
}
```

### 3. Generate the route cache

```bash
composer route:generate
# Output: Route cache generated: 1 route(s) written to storage/routes.php
```

### 4. Create the front controller

**`public/index.php`**

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Fishyboat21\SimpleApi\SimpleApi;

$api = new SimpleApi(
    routeCacheFile: __DIR__ . '/../storage/routes.php',
);

$api->run();
```

### 5. Start the server

```bash
php -S localhost:8000 -t public/
# → GET http://localhost:8000/api/health
# → {"status":200,"message":"OK","data":{"status":"healthy"}}
```

---

## Routing Convention

The filesystem path *is* the URL path. Create a file — it becomes a route.

| File | URL | Method |
|---|---|---|
| `src/Api/Health/Index.php` | `GET /api/health` | Declared via `#[Route]` |
| `src/Api/Users/Index.php` | `GET,POST /api/users` | Multiple `#[Route]` on one class |
| `src/Api/Users/_id/Index.php` | `GET /api/users/{id}` | Params via `_` prefix |

### Rules

1. **Files go in `src/Api/`** (configurable via `--scan`)
2. **`Index.php`** is the collection root — stripped from the URL
3. **`_underscore` prefix** creates a path parameter — `_id` becomes `{id}`
4. **Directories** become URL path segments, **all lowercased**
5. **`#[Route]` attribute** declares the HTTP method(s)
6. **Regenerate the cache** after adding or changing any endpoint: `composer route:generate`

### Path parameters

Path parameter values are accessed on the `Request` object:

```php
// src/Api/Users/_id/Index.php → GET /api/users/42
#[Route(Method::GET)]
class Index implements ApiHandler
{
    public function handle(Request $request): Response
    {
        $userId = $request->getAttribute('id'); // '42'

        return Response::ok([
            'userId' => $userId,
        ]);
    }
}
```

Multiple parameters are supported — just nest `_`-prefixed directories:

```
src/Api/Organizations/_orgId/Members/_memberId/Index.php
→ GET /api/organizations/{orgId}/members/{memberId}
```

---

## Creating Endpoints

### Single-method endpoint

A class-level `#[Route]` dispatches to a `handle()` method by convention:

```php
<?php

namespace App\Api\Products;

use Fishyboat21\SimpleApi\Attribute\Route;
use Fishyboat21\SimpleApi\Enum\Method;
use Fishyboat21\SimpleApi\Http\Request;
use Fishyboat21\SimpleApi\Http\Response;
use Fishyboat21\SimpleApi\Interface\ApiHandler;

#[Route(Method::GET)]
class Index implements ApiHandler
{
    public function handle(Request $request): Response
    {
        return Response::ok([
            'products' => [/* ... */],
        ]);
    }
}
```

### Multi-method endpoint (separate methods per verb)

Use method-level `#[Route]` attributes to dispatch directly to named methods:

```php
<?php

namespace App\Api\Users;

use Fishyboat21\SimpleApi\Attribute\Route;
use Fishyboat21\SimpleApi\Enum\Method;
use Fishyboat21\SimpleApi\Http\Request;
use Fishyboat21\SimpleApi\Http\Response;
use Fishyboat21\SimpleApi\Interface\ApiHandler;

class Index implements ApiHandler
{
    #[Route(Method::GET)]
    public function list(Request $request): Response
    {
        return Response::ok([/* all users */]);
    }

    #[Route(Method::POST)]
    public function create(Request $request): Response
    {
        $name = $request->body('name');
        // ... create user ...

        return Response::created(['id' => 1], 'User created');
    }
}
```

---

## Request Handling

The `Request` object provides access to every part of the incoming request:

```php
public function handle(Request $request): Response
{
    // Query string: /api/search?q=hello&page=2
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

    // Path parameters (set by the router)
    $id     = $request->getAttribute('id');  // from /api/users/{id}

    // Request metadata
    $method = $request->method();            // 'GET', 'POST', etc.
    $uri    = $request->uri();               // '/api/users/42?include=posts'
    $path   = $request->path();              // '/api/users/42' (no query string)

    return Response::ok(/* ... */);
}
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

// Manual construction
new Response(status: 200, message: 'OK', data: ['key' => 'value']);
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

CORS is configurable per-origin, with safe defaults (allow all origins, common methods).

```php
<?php
// public/index.php
require __DIR__ . '/../vendor/autoload.php';

use Fishyboat21\SimpleApi\Config\CorsConfig;
use Fishyboat21\SimpleApi\SimpleApi;

$cors = new CorsConfig(
    allowedOrigins: ['https://myapp.com', 'https://admin.myapp.com'],
    allowedMethods: ['GET', 'POST', 'PUT', 'DELETE'],
    allowedHeaders: ['Content-Type', 'Authorization'],
    allowCredentials: true,
    maxAge: 3600,  // preflight cache in seconds
);

$api = new SimpleApi(
    routeCacheFile: __DIR__ . '/../storage/routes.php',
    corsConfig: $cors,
);

$api->run();
```

The default `CorsConfig` allows all origins (`*`), all common HTTP methods, and common headers. Pass a custom `CorsConfig` to restrict origins, enable credentials, or limit allowed methods.

> **Note:** Using `allowedOrigins: ['*']` together with `allowCredentials: true` will throw a `RuntimeException` — the CORS spec forbids this combination.

---

## Error Handling & Logging

### Error responses

The framework automatically returns proper JSON error responses:

| Condition | Status | Body |
|---|---|---|
| Route not found | 404 | `{"status":404,"message":"Not Found"}` |
| Method not allowed | 405 | `{"status":405,"message":"Method Not Allowed"}` + `Allow` header |
| Unhandled exception | 500 | `{"status":500,"message":"Internal Server Error"}` |
| Custom `HttpException` | *varies* | Status from exception, safe message exposed |

> **Security:** Unhandled exceptions (500) return a generic `"Internal Server Error"` message to the client. The real exception message is never exposed — instead, it is passed to the configured logger.

### Attaching a logger

```php
$api = new SimpleApi(routeCacheFile: '...');
$api->setLogger($myPsr3Logger);  // any object with an error() method
$api->run();
```

### Custom HTTP exceptions in handlers

```php
use Fishyboat21\SimpleApi\Exception\HttpException;

// Throw custom HTTP errors from your handlers
throw new \Fishyboat21\SimpleApi\Exception\NotFoundException('User not found');
// → 404 {"status":404,"message":"User not found"}

// Or create your own exception class:
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

## Route Cache Commands

```bash
# Generate the cache (default paths)
composer route:generate

# Custom paths
vendor/bin/simple-api route:generate \
    --scan=src/Api \
    --namespace="App\\Api" \
    --output=storage/routes.php \
    --base-url=api
```

Re-run this command whenever you add, remove, or rename endpoint files. The cache is a plain PHP array loaded via `require` and cached by OPcache in production.

---

## Custom Base Path

By default, routes are prefixed with `/api`. Change this in the constructor:

```php
// No prefix — routes at /
$api = new SimpleApi(routeCacheFile: '...', baseUrlPath: '');

// Different prefix
$api = new SimpleApi(routeCacheFile: '...', baseUrlPath: 'v2');
// Routes at /v2/health, /v2/users, etc.
```

---

## Complete Example

**`src/Api/Tasks/_id/Index.php`**

```php
<?php

namespace App\Api\Tasks\_id;

use Fishyboat21\SimpleApi\Attribute\Route;
use Fishyboat21\SimpleApi\Enum\Method;
use Fishyboat21\SimpleApi\Exception\NotFoundException;
use Fishyboat21\SimpleApi\Http\Request;
use Fishyboat21\SimpleApi\Http\Response;
use Fishyboat21\SimpleApi\Interface\ApiHandler;

class Index implements ApiHandler
{
    #[Route(Method::GET)]
    public function show(Request $request): Response
    {
        $taskId = (int) $request->getAttribute('id');

        $task = $this->findTask($taskId);
        if ($task === null) {
            throw new NotFoundException("Task {$taskId} not found");
        }

        return Response::ok($task);
    }

    #[Route(Method::PUT)]
    public function update(Request $request): Response
    {
        $taskId = (int) $request->getAttribute('id');
        $title = $request->body('title');
        $done = $request->body('done', false);

        $task = $this->updateTask($taskId, $title, $done);

        return Response::ok($task, 'Task updated');
    }

    #[Route(Method::DELETE)]
    public function delete(Request $request): Response
    {
        $taskId = (int) $request->getAttribute('id');
        $this->removeTask($taskId);

        return Response::noContent();
    }

    // ... data access methods ...
}
```

After creating the file, run `composer route:generate` and the following routes are live:

```
GET    /api/tasks/42  →  App\Api\Tasks\_id\Index::show()
PUT    /api/tasks/42  →  App\Api\Tasks\_id\Index::update()
DELETE /api/tasks/42  →  App\Api\Tasks\_id\Index::delete()
```

---

## Performance

- **Route cache** loaded via `require` — OPcache caches the trie in shared memory
- **O(depth) route matching** — typically 2–5 array lookups per request, no regex, no linear scan
- **Lazy handler loading** — the matched handler class is autoloaded only after a successful match
- **Lazy request body parsing** — `php://input` is read only if the handler calls `body()` or `bodyData()`
- **Zero filesystem scanning** at runtime — the route cache is pre-built
- **No middleware stack** overhead — fixed pipeline: CORS → match → dispatch → send

---

## License

MIT
