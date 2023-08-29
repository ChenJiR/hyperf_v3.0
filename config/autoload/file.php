<?php
use function Hyperf\Support\env;

return [
    // 选择storage下对应驱动的键即可。
    'default' => 'local',
    'storage' => [
        'local' => [
            'driver' => \Hyperf\Filesystem\Adapter\LocalAdapterFactory::class,
            'root' => __DIR__ . '/../../runtime/file',
        ],
        'memory' => [
            'driver' => \Hyperf\Filesystem\Adapter\MemoryAdapterFactory::class,
        ],
        'oss' => [],
        'ossnm' => []
    ],
];
