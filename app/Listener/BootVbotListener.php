<?php

declare(strict_types=1);

namespace App\Listener;

use App\Service\VbotService;
use Hanson\Vbot\Foundation\Vbot;
use Hyperf\Collection\Collection;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\MainWorkerStart;
use Hyperf\Framework\Event\OnManagerStart;
use Hyperf\Framework\Event\OnStart;
use Hyperf\Server\Event\MainCoroutineServerStart;
use Psr\Container\ContainerInterface;
use Throwable;

#[Listener]
class BootVbotListener implements ListenerInterface
{
    public function __construct(protected ContainerInterface $container)
    {
    }

    public function listen(): array
    {
        return [
            MainWorkerStart::class,
        ];
    }

    public function process(object $event): void
    {
        go(function () {
            $pimple = new Vbot([]);
            $pimple->messageHandler->setHandler(fn(Collection $message) => $this->container->get(VbotService::class)->handle($message));

            $max = 10;
            while ($max-- > 0) {
                try {
                    $pimple->server->serve();
                } catch (Throwable $exception) {
                    di()->get(StdoutLoggerInterface::class)->error((string)$exception);
                    sleep(10);
                }
            }

            di()->get(StdoutLoggerInterface::class)->error('微信机器人已停止，请重启服务');
        });
    }
}