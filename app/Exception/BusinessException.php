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

class BusinessException extends BaseException
{
    public function __construct(?string $message = null, Throwable $previous = null)
    {
        parent::__construct($this->responseCode(), $message, $previous);
    }

    function responseCode(): int
    {
        return ResponseCode::BUSINESS_ERROR;
    }
}
