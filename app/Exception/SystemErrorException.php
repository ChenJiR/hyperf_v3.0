<?php
declare(strict_types=1);

namespace App\Exception;


use App\Constants\ResponseCode;
use App\Logger\Log;
use Throwable;

class SystemErrorException extends BaseException
{

    public function __construct(string $message = null, Throwable $previous = null)
    {
        Log::exceptionError($this);
        parent::__construct($this->responseCode(), $message, $previous);
    }

    function responseCode(): int
    {
        return ResponseCode::SERVER_ERROR;
    }
}
