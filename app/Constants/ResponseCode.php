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

namespace App\Constants;

use Hyperf\Constants\AbstractConstants;
use Hyperf\Constants\Annotation\Constants;

/**
 * @method static string getMessage(int $code)
 */
#[Constants]
class ResponseCode extends AbstractConstants
{
    /**
     * @Message("Success！")
     */
    public const SUCCESS = 0;

    /**
     * @Message("Server Error！")
     */
    public const BUSINESS_ERROR = 1;

    /**
     * @Message("未登录！")
     */
    public const NOT_LOGIN = -1;

    /**
     * @Message("Server Error！")
     */
    public const SERVER_ERROR = 500;

    /**
     * @Message("Server Error！")
     */
    public const HTTP_CLIENT_ERROR = 502;
}
