<?php

namespace Fishyboat21\SimpleApi\Attribute;

use Fishyboat21\SimpleApi\Enum\Method;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Route
{
    public function __construct(
        public readonly Method $method,
    ) {}
}
