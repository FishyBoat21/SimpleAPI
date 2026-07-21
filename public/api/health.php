<?php

/**
 * GET /api/health — Simple health-check endpoint.
 *
 * Returns the API status. A good starting point for adding your own endpoints.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Fishyboat21\SimpleApi\Endpoint;
use Fishyboat21\SimpleApi\Http\Request;
use Fishyboat21\SimpleApi\Http\Response;

$api = new Endpoint();

$api->get(function (Request $request): Response {
    return Response::ok(['status' => 'healthy']);
});

$api->run();
