<?php

namespace App\Middleware;


use App\Component\Cache\RedisCache;
use App\Exception\AuthException;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuthMiddleware implements MiddlewareInterface
{

    #[Inject]
    protected HttpResponse $response;

    #[Inject]
    protected RequestInterface $request;

    protected ?RedisCache $redisCache = null;

    protected string $need_login;

    protected string $login_type;


    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            // todo auth
        } catch (AuthException $e) {
            return $this->response->json(['code' => $e->getCode(), 'msg' => $e->getMessage(), 'data' => []]);
        }
        return $handler->handle($request);
    }

}