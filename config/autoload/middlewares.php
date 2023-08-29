<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

use App\Middleware\CorsMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\RequestMiddleware;
use App\Middleware\SignMiddleware;

return [
    'http' => [
        CorsMiddleware::class,  //跨域中间件
        RateLimitMiddleware::class,  //限流中间件
        RequestMiddleware::class,  //总请求中间件
        SignMiddleware::class,   //验签中间件
    ],
];
