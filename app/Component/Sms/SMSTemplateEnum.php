<?php

namespace App\Component\Sms;

use App\Util\Enum;

/**
 * @method static SMSTemplateEnum CAPTCHA_CODE() 验证码
 * @method static SMSTemplateEnum MESSAGE_BOARD_REPLY() 留言回复
 * @method static SMSTemplateEnum MESSAGE_BOARD_REPLY_V2() 留言回复
 * @method static SMSTemplateEnum DY_MEETING_NOTICE() 大河云会议通知
 * @method static SMSTemplateEnum DY_LOGIN_NOTICE() 大河云登录通知
 * @method static SMSTemplateEnum DY_CAPTCHA_CODE() 大河云登录验证码
 * @method static SMSTemplateEnum DY_CHANGE_PHONE_CODE() 大河云更换手机验证码
 * @method static SMSTemplateEnum WITHDRAW_CAPTCHA() 提现验证码
 * @method static SMSTemplateEnum DRAW_WHEEL_DEAILY_CAPTCHA() 提现验证码
 * @method static SMSTemplateEnum DRAW_WHEEL_CAKE_CAPTCHA() 提现验证码
 * @method static SMSTemplateEnum DRAW_CAR_CAPTCHA() 提现验证码
 * @method static SMSTemplateEnum DRAW_SUMMER_CAPTCHA() 提现验证码
 * @method static SMSTemplateEnum PASSWORDRESET_CAPTCHA() 重置密码验证码
 */
class SMSTemplateEnum extends Enum
{
    //验证码
    const CAPTCHA_CODE = ['captcha', 'a80f90dc883b4cc181ca5e20de680507', 1, '8822062436634'];
    const MESSAGE_BOARD_REPLY = ['message_board_reply', 'e4789f674f57480bb51643a151c5b39e', 1, '8822070738015'];
    const MESSAGE_BOARD_REPLY_V2 = ['message_board_reply_v2', 'ca9d167c37a14e13a05349bc69d7e2b8', 2, '8822070738015'];
    const DY_MEETING_NOTICE = ['meeting_notice', '', 1, '8822070738015'];
    const DY_LOGIN_NOTICE = ['login_notice', '', 1, '8822070738015'];
    const DY_CAPTCHA_CODE = ['dy_captcha', '4f8a7728c71040f29b453e14bb5b445d', 1, '8822062436634'];
    const DY_CHANGE_PHONE_CODE = ['change_phone', '263f32dee1ca4d39a3c310b90727d65b', 1, '8822062436634'];
    const WITHDRAW_CAPTCHA = ['withdraw_captcha', '8a34e40a9102401381178547ea681014', 1, '8822062436634'];
    const DRAW_WHEEL_DEAILY_CAPTCHA = ['draw_wheel_deaily_captcha', 'ddfcc7ab7f8c4486a1ad08da8144432d', 1, '8822070738015'];//幸运转盘短信通知模板_报业大厦二楼东厅
    const DRAW_WHEEL_CAKE_CAPTCHA = ['draw_wheel_cake_captcha', '05d546a2d10b45ef8bf74bc0f10dbc43', 0, '8822070738015'];//幸运转盘短信通知模板_福晶园
    const DRAW_CAR_CAPTCHA = ['draw_car_captcha', '73d7667881b84943939b7b45257836b7', 0, '8822070738015'];//无门槛短信通知模板文案_购车大礼包
    const DRAW_SUMMER_CAPTCHA = ['draw_summer_captcha', '7283f965fd234d519fa22f4268a251cc', 0, '8822070738015'];//无门槛短信通知模板文案_夏装节优惠券
    const PASSWORDRESET_CAPTCHA = ['passwordreset_captcha', 'f5ab7a4f5dcc4b369237f37292372c72', 1, '8822070738015'];//重置密码短信验证码

    public function getCode()
    {
        return static::$value[0];
    }

    public function getTemplateId()
    {
        return static::$value[1];
    }

    public function getParamsLength()
    {
        return static::$value[2];
    }

    public function getSender()
    {
        return static::$value[3];
    }

    public static function getTemplateByCode($code): ?SMSTemplateEnum
    {
        if (!$code) return null;

        foreach (static::toArray() as $item) {
            if ($code == $item[0]) {
                return new static($item);
            }
        }
        return null;
    }
}