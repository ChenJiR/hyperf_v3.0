<?php

namespace App\Middleware;

use App\Exception\SignException;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use function Hyperf\Support\env;
use function in_array;

class SignMiddleware implements MiddlewareInterface
{

    #[Inject]
    protected HttpResponse $response;

    #[Inject]
    protected RequestInterface $request;

    // 验签白名单
    const SIGN_WHITE_LIST = [];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {

        if (in_array($this->request->path(), self::SIGN_WHITE_LIST)) return $handler->handle($request);

        // 本地local环境，为方便调试不再验签
        if (env('APP_ENV') == 'local') return $handler->handle($request);

        try {
            // todo checksign
        } catch (SignException $e) {
            return $this->response->json(['code' => $e->getCode(), 'msg' => $e->getMessage(), 'data' => []]);
        }
        return $handler->handle($request);
    }

}