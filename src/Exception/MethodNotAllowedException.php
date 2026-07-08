<?php

namespace Fishyboat21\SimpleApi\Exception;

class MethodNotAllowedException extends HttpException
{
    /** @var string[] */
    public readonly array $allowedMethods;

    /**
     * @param string[] $allowedMethods HTTP methods allowed for this route.
     * @param string   $message        Human-readable error message.
     */
    public function __construct(
        array $allowedMethods = [],
        string $message = 'Method Not Allowed',
    ) {
        parent::__construct($message, 405);
        $this->allowedMethods = $allowedMethods;
    }

    public function getStatusCode(): int
    {
        return 405;
    }

    /** Get the value for the Allow response header. */
    public function getAllowHeader(): string
    {
        return implode(', ', $this->allowedMethods);
    }
}
