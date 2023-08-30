<?php

namespace App\Component\Notice;

use Hyperf\Command\Command;
use Hyperf\HttpServer\Contract\RequestInterface;
use Symfony\Component\Console\Input\InputInterface;
use Throwable;

interface NoticeInterface
{
    const PROJECT = '';

    const CARD_MSG_DEBUG = 'debug';
    const CARD_MSG_INFO = 'info';
    const CARD_MSG_WARN = 'warn';
    const CARD_MSG_ERROR = 'error';

    public function textMsg(string $text, $remind_time = null, string $project = self::PROJECT);

    public function cardMsg(string $level, string $title, string $msg, array $data = [], $remind_time = null, string $project = self::PROJECT);

    public function httpError(Throwable $e, ?RequestInterface $request = null, $remind_time = null, string $project = self::PROJECT);

    public function commandError(Throwable $e, Command $command, InputInterface $input, $remind_time = null, string $project = self::PROJECT);

}