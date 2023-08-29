<?php
declare(strict_types=1);

namespace App\Middleware;

use Hyperf\Contract\Arrayable;
use Hyperf\Contract\Jsonable;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\Codec\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use stdClass;

class CoreMiddleware extends \Hyperf\HttpServer\CoreMiddleware
{
    protected function transferToResponse($response, ServerRequestInterface $request): ResponseInterface
    {
        if (is_string($response)) {
            return $this->response()->withAddedHeader('content-type', 'text/plain')->withBody(new SwooleStream($response));
        }

        if (is_array($response) || $response instanceof Arrayable || $response instanceof stdClass) {
            return $this->response()
                ->withAddedHeader('content-type', 'application/json;charset=UTF-8')
                ->withBody(new SwooleStream(Json::encode($response)));
        }

        if ($response instanceof Jsonable) {
            return $this->response()
                ->withAddedHeader('content-type', 'application/json;charset=UTF-8')
                ->withBody(new SwooleStream((string)$response));
        }

        return $this->response()->withAddedHeader('content-type', 'text/plain')->withBody(new SwooleStream((string)$response));
    }

    protected function handleNotFound(ServerRequestInterface $request): ResponseInterface
    {
        return $this->response()->withStatus(404)->withBody(new SwooleStream('Not Found.'));
    }

}