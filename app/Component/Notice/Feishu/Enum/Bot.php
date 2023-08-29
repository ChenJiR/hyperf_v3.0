<?php

namespace App\Component\Notice\Feishu\Enum;

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
    const PHP_BOT = ['PHP_BOT', 'https://open.feishu.cn/open-apis/bot/v2/hook/'];
    //JAVA
    const JAVA_BOT = ['JAVA_BOT', 'https://open.feishu.cn/open-apis/bot/v2/hook/'];

    public function getBotHookUrl(): string
    {
        return env('FEISHU.' . static::$value[0], static::$value[1]);
    }

    public static function getBot($project): Bot
    {
        return match ($project) {
            default => static::PHP_BOT(),
            'java' => static::JAVA_BOT(),
        };
    }
}