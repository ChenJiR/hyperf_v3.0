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

class LocationException extends BaseException
{
    public function __construct(?string $message = null, ?string $lng = null, ?string $lat = null, Throwable $previous = null)
    {
        Log::error($message, ['lng' => $lng, 'lat' => $lat], 'location');
        parent::__construct($this->responseCode(), $message, $previous);
    }

    function responseCode(): int
    {
        return ResponseCode::HTTP_CLIENT_ERROR;
    }
}
