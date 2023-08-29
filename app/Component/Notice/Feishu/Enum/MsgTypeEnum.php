<?php

namespace App\Component\Notice\Feishu\Enum;


use App\Util\Enum;

/**
 * Class MsgTypeEnum
 *
 * @method static MsgTypeEnum MSG_TYPE_CARD()
 * @method static MsgTypeEnum MSG_TYPE_TEXT()
 * @method static MsgTypeEnum MSG_TYPE_POST()
 * @method static MsgTypeEnum MSG_TYPE_SHARECHAT()
 * @method static MsgTypeEnum MSG_TYPE_IMAGE()
 */
class MsgTypeEnum extends Enum
{
    //卡片
    const MSG_TYPE_CARD = ['interactive', 'card'];
    //纯文字
    const MSG_TYPE_TEXT = ['text', 'content'];
    //富文本
    const MSG_TYPE_POST = ['post', 'content'];
    //群名片
    const MSG_TYPE_SHARECHAT = ['share_chat', 'content'];
    //图片
    const MSG_TYPE_IMAGE = ['image', 'content'];

    public function getMsgType()
    {
        return static::$value[0];
    }

    public function getContentKey()
    {
        return static::$value[1];
    }
}