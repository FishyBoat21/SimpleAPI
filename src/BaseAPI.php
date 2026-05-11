<?php
namespace Fishyboat21\SimpleApi;

use Closure;
use Fishyboat21\SimpleApi\Enum\Method;
use Fishyboat21\SimpleApi\Interface\IResponse;
use Fishyboat21\SimpleApi\Models\Exceptions\MethodNotSupportedException;
use Fishyboat21\SimpleApi\Models\ResponseError;
use Throwable;

class BaseAPI{
    protected array $handlers;
    
    public function addHandler(Method $method, Closure $handler):self{
        $this->handlers[$method->value] = $handler;
        return $this;
    }
    
    public function run():void{
        try{
            if(!isset($handler[$_SERVER['REQUEST_METHOD']])){
                throw new MethodNotSupportedException("Method not supported!");
            }
            $result = $this->handlers[$_SERVER['REQUEST_METHOD']]();
            $this->sendResponse($result);
        }catch(MethodNotSupportedException $th){
            $response = new ResponseError();
            $response->status = 405;
            $response->message = $th->getMessage();
            $this->sendResponse($response);
        }catch(Throwable $th){
            $response = new ResponseError();
            $response->status = 500;
            $response->message = $th->getMessage();
            $this->sendResponse($response);
        }
    }

    protected function sendResponse(IResponse $response):void{
        http_response_code($response->status);
        echo json_encode($response);
        exit;
    }
}