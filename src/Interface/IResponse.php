<?php

namespace Fishyboat21\SimpleApi\Interface;

interface IResponse{
    public int $status {get;set;}
    public string $message {get;set;}
}