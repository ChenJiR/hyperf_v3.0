<?php

namespace App\Component\Notice\DingDing\Enum;

use App\Util\Enum;
use function Hyperf\Support\env;

/**
 * Class MsgTypeEnum
 *
 * @method static Bot PHP_BOT()
 * @method static Bot JAVA_BOT()
 */
class Bot extends Enum
{
    //PHP
    const PHP_BOT = [
        'PHP_BOT',
        'https://oapi.dingtalk.com/robot/send?access_token=',
        'PHP_BOT_SECRET',
        ''
    ];

    //JAVA
    const JAVA_BOT = [
        'JAVA_BOT',
        'https://oapi.dingtalk.com/robot/send?access_token=',
        'JAVA_BOT_SECRET',
        ''
    ];

    public function getBotHookUrl(): string
    {
        return env('DINGDING.' . static::$value[0], static::$value[1]);
    }

    public function getBotSecret(): ?string
    {
        return env('DINGDING.' . static::$value[2], static::$value[3]);
    }

    public static function getBot($project): Bot
    {
        return match ($project) {
            default => static::PHP_BOT(),
            'java' => static::JAVA_BOT(),
        };
    }
}