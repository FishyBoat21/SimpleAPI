<?php

namespace App\Api\Health;

use Fishyboat21\SimpleApi\Attribute\Route;
use Fishyboat21\SimpleApi\Enum\Method;
use Fishyboat21\SimpleApi\Http\Request;
use Fishyboat21\SimpleApi\Http\Response;
use Fishyboat21\SimpleApi\Interface\ApiHandler;

#[Route(Method::GET)]
class Index implements ApiHandler
{
    public function handle(Request $request): Response
    {
        return Response::ok(['status' => 'healthy']);
    }
}
