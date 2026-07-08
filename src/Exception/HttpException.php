<?php

namespace Fishyboat21\SimpleApi\Exception;

use RuntimeException;

/**
 * Base exception for HTTP error responses.
 *
 * Subclasses map to specific HTTP status codes. These are safe to expose
 * to the client because their messages are user-facing routing information,
 * not internal error details.
 */
abstract class HttpException extends RuntimeException
{
    abstract public function getStatusCode(): int;
}
