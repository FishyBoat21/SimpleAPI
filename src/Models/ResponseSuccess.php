<?php

namespace Fishyboat21\SimpleApi\Models;

use Fishyboat21\SimpleApi\Interface\IResponse;

/**
 * @deprecated Use {@see \Fishyboat21\SimpleApi\Http\Response::ok()} or
 *             {@see \Fishyboat21\SimpleApi\Http\Response::created()} instead.
 *             Kept for backward compatibility; will be removed in v3.0.
 */
class ResponseSuccess implements IResponse{
    public int $status = 200;
    public string $message = "";
    public object|array|null $data = null;
}