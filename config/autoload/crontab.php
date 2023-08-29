<?php declare(strict_types=1);

$appEnv = Hyperf\Support\env('APP_ENV', 'prod');

return [
    // 是否开启定时任务
    'enable' => match ($appEnv) {
        'prod', 'dev' => true,
        default => false
    }
];