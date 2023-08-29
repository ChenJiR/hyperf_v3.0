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

use App\Middleware\CoreMiddleware;
use App\Util\MyLengthAwarePaginator;
use Hyperf\Contract\LengthAwarePaginatorInterface;

use App\Component\Notice\NoticeInterface;
use App\Component\Notice\Feishu\FeishuNotice;
use App\Component\Notice\DingDing\DingdingNotice;

return [
    Hyperf\HttpServer\CoreMiddleware::class => CoreMiddleware::class,
    LengthAwarePaginatorInterface::class => MyLengthAwarePaginator::class,
    Hyperf\Contract\StdoutLoggerInterface::class => App\Kernel\Log\LoggerFactory::class,
    NoticeInterface::class => DingdingNotice::class,
];
