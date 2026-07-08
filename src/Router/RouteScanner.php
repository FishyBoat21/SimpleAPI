<?php

namespace Fishyboat21\SimpleApi\Router;

use Fishyboat21\SimpleApi\Attribute\Route;
use Fishyboat21\SimpleApi\Interface\ApiHandler;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveRegexIterator;
use RegexIterator;
use ReflectionClass;
use RuntimeException;

/**
 * Scans a directory for ApiHandler classes and builds a route trie.
 *
 * Convention: file path relative to the scan directory maps to URL path.
 *   - Directories/files prefixed with _ become path parameters
 *   - "Index" segment is removed (resource root)
 *   - All segments are lowercased
 *
 * Example:
 *   src/Api/Users/_id/Index.php → /users/{id}
 */
class RouteScanner
{
    /** @var array<string, string> Cache of file path → FQCN */
    private array $classMap = [];

    /**
     * @param string $scanDir       Absolute path to scan for endpoint classes.
     * @param string $baseNamespace PSR-4 namespace prefix for classes in scanDir.
     */
    public function __construct(
        private readonly string $scanDir,
        private readonly string $baseNamespace,
    ) {}

    /**
     * Scan for endpoint classes and build the route trie.
     *
     * @return array The trie structure suitable for RouteCollection.
     * @throws RuntimeException On duplicate routes.
     */
    public function scan(): array
    {
        $trie = [];
        $seen = []; // "METHOD /path" → file path for duplicate detection

        foreach ($this->findPhpFiles() as $filePath) {
            $fqcn = $this->resolveClassName($filePath);
            if ($fqcn === null || !class_exists($fqcn)) {
                continue;
            }

            $reflection = new ReflectionClass($fqcn);
            if (!$reflection->implementsInterface(ApiHandler::class) || $reflection->isAbstract()) {
                continue;
            }

            $urlPath = $this->deriveUrlPath($filePath);
            $routes = $this->extractRoutes($reflection);

            foreach ($routes as [$method, $handlerMethod]) {
                $key = "{$method} {$urlPath}";
                if (isset($seen[$key])) {
                    throw new RuntimeException(
                        "Duplicate route: {$method} {$urlPath} — " .
                        "declared in {$seen[$key]} and {$filePath}"
                    );
                }
                $seen[$key] = $filePath;

                $this->insertRoute($trie, $urlPath, $method, $fqcn, $handlerMethod);
            }
        }

        return $trie;
    }

    // ── File discovery ───────────────────────────────────────

    /** @return string[] Absolute paths to PHP files under scanDir. */
    private function findPhpFiles(): array
    {
        if (!is_dir($this->scanDir)) {
            return [];
        }

        // SKIP_DOTS only — no FOLLOW_SYMLINKS to prevent traversal outside scanDir
        $directory = new RecursiveDirectoryIterator(
            $this->scanDir,
            RecursiveDirectoryIterator::SKIP_DOTS
        );
        $iterator = new RecursiveIteratorIterator($directory);
        $phpFiles = new RegexIterator($iterator, '/\.php$/', RecursiveRegexIterator::MATCH);

        $paths = [];
        foreach ($phpFiles as $file) {
            $paths[] = $file->getPathname();
        }
        sort($paths);
        return $paths;
    }

    // ── Class name resolution ────────────────────────────────

    /**
     * Derive the FQCN from a file path using PSR-4 convention.
     *
     * scanDir = /app/src/Api, baseNamespace = App\Api
     * filePath = /app/src/Api/Users/Index.php
     * relative  = Users/Index.php
     * FQCN      = App\Api\Users\Index
     */
    private function resolveClassName(string $filePath): ?string
    {
        if (isset($this->classMap[$filePath])) {
            return $this->classMap[$filePath];
        }

        $normalizedScanDir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->scanDir);
        $normalizedScanDir = rtrim($normalizedScanDir, DIRECTORY_SEPARATOR);
        $normalizedFilePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filePath);

        if (!str_starts_with($normalizedFilePath, $normalizedScanDir)) {
            return null;
        }

        $relative = ltrim(substr($normalizedFilePath, strlen($normalizedScanDir)), DIRECTORY_SEPARATOR);
        $relative = str_replace(DIRECTORY_SEPARATOR, '\\', $relative);
        $relative = preg_replace('/\.php$/i', '', $relative);

        $fqcn = rtrim($this->baseNamespace, '\\') . '\\' . $relative;
        $this->classMap[$filePath] = $fqcn;
        return $fqcn;
    }

    // ── URL path derivation ──────────────────────────────────

    /**
     * Derive the URL path from the file path using the convention.
     *
     * FilePath:  /app/src/Api/Users/_id/Index.php
     * Relative:  Users/_id/Index
     * Remove trailing /Index → Users/_id
     * Replace _ segments → Users/{id}
     * Lowercase    → /users/{id}
     * Prepend base → /api/users/{id}
     */
    private function deriveUrlPath(string $filePath): string
    {
        // Get relative path from scanDir
        $normalizedScanDir = rtrim(
            str_replace(['/', '\\'], '/', $this->scanDir),
            '/'
        );
        $normalizedPath = str_replace('\\', '/', $filePath);

        $relative = ltrim(substr($normalizedPath, strlen($normalizedScanDir)), '/');
        $relative = preg_replace('/\.php$/i', '', $relative);

        // Split into segments
        $segments = explode('/', $relative);

        // Remove trailing "Index" segment (collection root)
        $last = end($segments);
        if (strcasecmp($last, 'Index') === 0) {
            array_pop($segments);
        }

        // Transform segments
        $urlSegments = [];
        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }
            if (str_starts_with($segment, '_')) {
                // Path parameter: _id → {id}
                $urlSegments[] = '{' . substr($segment, 1) . '}';
            } else {
                $urlSegments[] = strtolower($segment);
            }
        }

        $urlPath = '/' . implode('/', $urlSegments);

        // Normalize trailing slash
        $urlPath = $urlPath === '/' ? '/' : rtrim($urlPath, '/');

        return $urlPath;
    }

    // ── Route extraction via reflection ──────────────────────

    /**
     * Extract [method, handlerMethod] pairs from a handler class.
     *
     * - Method-level #[Route] attributes target that specific method.
     * - Class-level #[Route] attributes target the "handle" method by convention.
     */
    private function extractRoutes(ReflectionClass $reflection): array
    {
        $routes = [];

        // Check methods first (more specific)
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->isAbstract()) {
                continue;
            }
            $attrs = $method->getAttributes(Route::class);
            foreach ($attrs as $attr) {
                /** @var Route $route */
                $route = $attr->newInstance();
                $routes[] = [$route->method->value, $method->getName()];
            }
        }

        // Check class-level attributes (targets handle() by convention)
        $classAttrs = $reflection->getAttributes(Route::class);
        foreach ($classAttrs as $attr) {
            /** @var Route $route */
            $route = $attr->newInstance();

            // Don't duplicate if a method already declares this method
            $alreadyDeclared = false;
            foreach ($routes as [$declaredMethod]) {
                if ($declaredMethod === $route->method->value) {
                    $alreadyDeclared = true;
                    break;
                }
            }

            if (!$alreadyDeclared) {
                $routes[] = [$route->method->value, 'handle'];
            }
        }

        return $routes;
    }

    // ── Trie insertion ───────────────────────────────────────

    /**
     * Insert a route into the trie structure.
     *
     * For /users/{id} with method GET → class Users\_id\Index::show:
     * trie['users']['*']['__name'] = 'id'
     * trie['users']['*']['__handlers']['GET'] = ['App\Api\Users\_id\Index', 'show']
     */
    private function insertRoute(
        array &$trie,
        string $urlPath,
        string $method,
        string $fqcn,
        string $handlerMethod
    ): void {
        $segments = explode('/', trim($urlPath, '/'));
        if ($segments === ['']) {
            $segments = [];
        }

        $node = &$trie;

        foreach ($segments as $segment) {
            if (str_starts_with($segment, '{') && str_ends_with($segment, '}')) {
                // Path parameter
                $paramName = substr($segment, 1, -1);
                if (!isset($node['*'])) {
                    $node['*'] = ['__name' => $paramName, '__handlers' => []];
                }
                $node = &$node['*'];
            } else {
                // Static segment
                if (!isset($node[$segment])) {
                    $node[$segment] = [];
                }
                $node = &$node[$segment];
            }
        }

        // Ensure __handlers exists at this node
        if (!isset($node['__handlers'])) {
            $node['__handlers'] = [];
        }

        $node['__handlers'][$method] = [$fqcn, $handlerMethod];
    }
}
