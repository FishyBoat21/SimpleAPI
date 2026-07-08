<?php

namespace Fishyboat21\SimpleApi\Models;

use Fishyboat21\SimpleApi\Interface\IResponse;

/**
 * @deprecated Use {@see \Fishyboat21\SimpleApi\Http\Response::error()} instead.
 *             Kept for backward compatibility; will be removed in v3.0.
 */
class ResponseError implements IResponse{
    public int $status = 500;
    public string $message = "";
}