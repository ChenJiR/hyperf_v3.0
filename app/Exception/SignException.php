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

namespace App\Exception;

use App\Constants\ResponseCode;
use Hyperf\Server\Exception\ServerException;
use Throwable;

class SignException extends BaseException
{

    const ERROR_PARAMS = '请求参数有误';
    const EMPTY_APPID = 'app_id不能为空';
    const ERROR_APPID = 'app_id参数有误';
    const EMPTY_TIMESTAMP = '请求发起时间不能为空';
    const ERROR_TIMESTAMP = '请求发起时间格式有误';
    const TIMESTAMP_EXPIRE = '请求发起时间与服务器时间差距过大';
    const EMPTY_SIGN = 'sign:不能为空';
    const ERROR_SIGN = 'sign:签名校验失败';
    const ABANDON_APP = '应用已被禁用';

    public ?string $sign = null;

    public function __construct(?string $message = null, ?string $sign = null, Throwable $previous = null)
    {
        $this->sign = $sign;
        parent::__construct($this->responseCode(), $message, $previous);
    }

    function responseCode(): int
    {
        return ResponseCode::BUSINESS_ERROR;
    }
}
