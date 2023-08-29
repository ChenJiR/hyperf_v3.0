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
use App\Logger\Log;
use Throwable;

class HttpClientException extends BaseException
{
    public function __construct(?string $message = null, array $http_data = [], Throwable $previous = null)
    {
        Log::error($message, $http_data, 'httpclient');
        parent::__construct($this->responseCode(), $message, $previous);
    }

    function responseCode(): int
    {
        return ResponseCode::HTTP_CLIENT_ERROR;
    }
}
