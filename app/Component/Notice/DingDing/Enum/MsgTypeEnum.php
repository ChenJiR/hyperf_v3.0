<?php

namespace App\Component\Notice\DingDing\Enum;


use App\Util\Enum;

/**
 * Class MsgTypeEnum
 *
 * @method static MsgTypeEnum MSG_TYPE_LINK()
 * @method static MsgTypeEnum MSG_TYPE_TEXT()
 * @method static MsgTypeEnum MSG_TYPE_MARKDOWN()
 * @method static MsgTypeEnum MSG_TYPE_ACTIONCARD()
 * @method static MsgTypeEnum MSG_TYPE_FEEDCARD()
 */
class MsgTypeEnum extends Enum
{
    //text类型
    const MSG_TYPE_TEXT = ['text', 'text'];
    //link类型
    const MSG_TYPE_LINK = ['link', 'link'];
    //markdown类型
    const MSG_TYPE_MARKDOWN = ['markdown', 'markdown'];
    //ActionCard类型
    const MSG_TYPE_ACTIONCARD = ['actionCard', 'actionCard'];
    //FeedCard类型
    const MSG_TYPE_FEEDCARD = ['feedCard', 'feedCard'];

    public function getMsgType()
    {
        return static::$value[0];
    }

    public function getContentKey()
    {
        return static::$value[1];
    }
}