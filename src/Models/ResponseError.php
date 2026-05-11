<?php

namespace Fishyboat21\SimpleApi\Models;

use Fishyboat21\SimpleApi\Interface\IResponse;

class ResponseError implements IResponse{
    public int $status;
    public string $message;
}