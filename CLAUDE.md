# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

`fishyboat21/simple-api` is a minimal PHP 8.4+ library for building JSON HTTP APIs using a per-file endpoint pattern. Each API endpoint is a single PHP file in `public/api/` — the web server serves each file directly. No front controller, no route cache, no code generation.

No build step, no framework, no ORM — just Composer autoloading.

## Namespace & Autoloading

PSR-4 mapping: `Fishyboat21\SimpleApi\` → `src/`

After adding new classes under `src/`, run:
```bash
composer dump-autoload
```

## Core Architecture

### Endpoint Helper (`src/Endpoint.php`)

The shared helper class used by every endpoint file. Each `public/api/*.php` file:

1. Requires Composer autoloader
2. Creates an `Endpoint` instance (optionally with a `CorsConfig`)
3. Registers handlers for HTTP methods (`get()`, `post()`, `put()`, `delete()`, `patch()`)
4. Calls `run()`

The `Endpoint::run()` pipeline:
1. Sets `Content-Type: application/json`
2. Creates `Request::fromGlobals()`
3. Handles CORS preflight (returns 204 for OPTIONS with Origin)
4. Finds handler matching request method (lowercase key)
5. Calls handler, wraps non-Response return values in `Response::ok()`
6. Applies CORS response headers
7. Sends JSON response

Error handling:
- `MethodNotAllowedException` → 405 with `Allow` header
- `HttpException` subclasses → their status code and message
- `Throwable` → generic "Internal Server Error" (real message logged via optional PSR-3 logger, never leaked)

### File-Per-Endpoint Pattern

```
public/api/health.php       →  GET  /api/health.php  (or /api/health with rewrite)
public/api/users.php        →  GET,POST /api/users.php
public/api/task.php?id=42   →  GET,PUT,DELETE /api/task.php?id=42
```

Each endpoint file registers handlers for one or more HTTP methods:

```php
$api = new Endpoint();
$api->get(fn(Request $r) => Response::ok(['data' => ...]));
$api->post(fn(Request $r) => Response::created(['id' => 1]));
$api->run();
```

### Request / Response (`src/Http/`)

- **`Request`** — `fromGlobals()` / `fromParts()`, lazy JSON body parsing, query/body/header accessors
- **`Response`** — factory methods: `ok()`, `created()`, `noContent()`, `notFound()`, `error()`, `setHeader()` for CORS/custom headers

### CORS (`src/Cors.php` + `src/Config/CorsConfig.php`)

Configurable per-endpoint via `new Endpoint(corsConfig: $cors)`. Defaults to allow all origins, all common methods.

### Exception Hierarchy (`src/Exception/`)

```
HttpException (abstract)
├── NotFoundException         → 404
└── MethodNotAllowedException → 405
```

## PHP Version

Requires PHP `>=8.4`.

## Key Performance Properties

- **No routing overhead** — web server serves PHP files directly
- **No route cache** — nothing to generate, nothing to load
- **Lazy body parsing** — `php://input` read only when handler calls `body()`
- **Fixed pipeline** — CORS → dispatch → send
- **Zero filesystem scanning** — no directory traversal at runtime
