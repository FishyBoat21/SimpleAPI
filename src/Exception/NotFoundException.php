<?php

namespace Fishyboat21\SimpleApi\Exception;

class NotFoundException extends HttpException
{
    public function __construct(string $message = 'Not Found')
    {
        parent::__construct($message, 404);
    }

    public function getStatusCode(): int
    {
        return 404;
    }
}
