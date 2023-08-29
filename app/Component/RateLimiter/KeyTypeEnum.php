<?php


namespace App\Component\RateLimiter;


use Hyperf\Constants\AbstractConstants;
use Hyperf\Constants\Annotation\Constants;
use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * @method static string getMessage(int $code)
 */
#[Constants]
class KeyTypeEnum extends AbstractConstants
{
    /**
     * @Message("用户IP")
     */
    const RATELIMIT_KEYTYPE_IP = 1;

    /**
     * @Message("用户token")
     */
    const RATELIMIT_KEYTYPE_TOKEN = 2;

    /**
     * @Message("用户设备ID")
     */
    const RATELIMIT_KEYTYPE_DEVICE = 3;

    /**
     * @Message("用户设备型号")
     */
    const RATELIMIT_KEYTYPE_PHONEMODEL = 4;

    /**
     * @Message("访问url")
     */
    const RATELIMIT_KEYTYPE_URL = 5;
}