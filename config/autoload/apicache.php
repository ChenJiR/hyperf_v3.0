<?php

use Hyperf\Di\Container;
use App\Util\CommonHelper;

return [
    'test/index' => [
        'skip' => function ($method, $inputdata, $container): bool {
            return false;
        },
        'cacheKey' => function ($method, $inputdata, $container): string {
            return 'testtttt' . $method;
        },
        'cacheTtl' => function ($method, $inputdata, $response): int {
            return 60 * 60;
        },
        'canCache' => function ($method, $inputdata, $response, $container): bool {
            return $response == 'test' ? true : false;
        }
    ]
];