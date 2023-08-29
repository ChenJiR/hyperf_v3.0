<?php

namespace App\Listener;

use App\Event\OnHttpRequest;
use Hyperf\Command\Event\BeforeHandle;
use Hyperf\Context\Context;
use Hyperf\Contract\OnRequestInterface;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BeforeWorkerStart;
use Hyperf\Framework\Event\OnManagerStart;
use Hyperf\Process\Event\BeforeProcessHandle;

#[Listener]
class RunEnvListener implements ListenerInterface
{

    const CONTEXT_RUN_ENV = 'run_env';

    const RUN_IN_CLI = 'cli';
    const RUN_IN_TASK = 'task';
    const RUN_IN_PROCESS = 'process';
    const RUN_IN_COMMON = 'common';

    public function listen(): array
    {
        return [
            BeforeHandle::class,
            BeforeWorkerStart::class,
            BeforeProcessHandle::class,
            OnHttpRequest::class
        ];
    }

    public function process(object $event): void
    {
        if ($event instanceof BeforeHandle) {
            Context::set(self::CONTEXT_RUN_ENV, self::RUN_IN_CLI);
        } else if ($event instanceof BeforeWorkerStart) {
            $event->server->taskworker
                ? Context::set(self::CONTEXT_RUN_ENV, self::RUN_IN_TASK)
                : Context::set(self::CONTEXT_RUN_ENV, self::RUN_IN_COMMON);
        } else if ($event instanceof BeforeProcessHandle) {
            Context::set(self::CONTEXT_RUN_ENV, self::RUN_IN_PROCESS);
        } else {
            Context::set(self::CONTEXT_RUN_ENV, self::RUN_IN_COMMON);
        }
    }

    public static function getEnv()
    {
        return Context::get(self::CONTEXT_RUN_ENV);
    }
}