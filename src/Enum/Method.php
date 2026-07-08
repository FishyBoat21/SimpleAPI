<?php

namespace Fishyboat21\SimpleApi\Enum;

enum Method:string{
    case POST = "POST";
    case GET = "GET";
    case PUT = "PUT";
    case DELETE = "DELETE";
    case PATCH = "PATCH";
    case HEAD = "HEAD";
    case OPTIONS = "OPTIONS";
}