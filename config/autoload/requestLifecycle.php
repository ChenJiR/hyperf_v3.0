<?php

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use App\Component\Cache\RedisCache;
use App\Constants\ResponseCode;

return [
    'test/index' => [
        'before' => function ($method, $inputdata, $container) {
            return;
        },
        'after' => function ($method, $inputdata, $response, $container) {
            return;
        }
    ]
];