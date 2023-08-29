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

use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\JsonFormatter;
use Monolog\Logger;
use App\Kernel\Log;
use function Hyperf\Support\env;

$appEnv = env('APP_ENV', 'prod');

$option = match ($appEnv) {
    'local' => [
        'handler' => [
            'class' => Monolog\Handler\StreamHandler::class,
            'constructor' => [
                'stream' => 'php://stdout',
                'level' => Logger::INFO,
            ],
        ],
        'formatter' => [
            'class' => LineFormatter::class,
            'constructor' => [
                'format' => "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                'dateFormat' => 'Y-m-d H:i:s',
                'allowInlineLineBreaks' => true,
                'includeStacktraces' => true,
            ],
        ],
        'processors' => [
            [
                'class' => Log\AppendRequestIdProcessor::class,
            ],
        ],
    ],
    'dev' => [
        'handler' => [
            'class' => \App\Logger\Handler\RotatingDirHandler::class,
            'constructor' => [
                'filename' => BASE_PATH . '/runtime/logs/hyperf.log',
                'level' => Logger::INFO,
            ],
        ],
        'formatter' => [
            'class' => LineFormatter::class,
            'constructor' => [
                'format' => "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                'dateFormat' => 'Y-m-d H:i:s',
                'allowInlineLineBreaks' => true,
                'includeStacktraces' => true,
            ],
        ],
        'processors' => [
            [
                'class' => Log\AppendRequestIdProcessor::class,
            ],
        ],
    ],
    default => [
        'handlers' => [
            [
                'class' => \App\Logger\Handler\RotatingDirHandler::class,
                'constructor' => [
                    'filename' => BASE_PATH . '/runtime/logs/info.log',
                    'level' => Logger::INFO,
                ],
                'formatter' => [
                    'class' => JsonFormatter::class,
                    'constructor' => [
                        'batchMode' => JsonFormatter::BATCH_MODE_JSON,
                        'appendNewline' => true,
                    ],
                ],
                'processors' => [
                    [
                        'class' => Log\AppendRequestIdProcessor::class,
                    ],
                ],
            ],
            [
                'class' => \App\Logger\Handler\RotatingDirHandler::class,
                'constructor' => [
                    'filename' => BASE_PATH . '/runtime/logs/error.log',
                    'level' => Logger::ERROR,
                ],
                'formatter' => [
                    'class' => JsonFormatter::class,
                    'constructor' => [
                        'batchMode' => JsonFormatter::BATCH_MODE_JSON,
                        'appendNewline' => true,
                    ],
                ],
                'processors' => [
                    [
                        'class' => Log\AppendRequestIdProcessor::class,
                    ],
                ],
            ],
        ]
    ],
};

return [
    'default' => $option,
    'rateLimit' => [
        'handler' => [
            'class' => \App\Logger\Handler\RotatingDirHandler::class,
            'constructor' => [
                'filename' => BASE_PATH . '/runtime/logs/rateLimit.log',
                'level' => Logger::INFO,
            ],
        ],
        'formatter' => [
            'class' => JsonFormatter::class,
            'constructor' => [
                'batchMode' => JsonFormatter::BATCH_MODE_JSON,
                'appendNewline' => true,
                'dateFormat' => 'Y-m-d H:i:s'
            ],
        ],
    ]
];
