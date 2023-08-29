<?php declare(strict_types=1);


namespace App\Exception;

use App\Constants\ResponseCode;
use Hyperf\Server\Exception\ServerException;
use Throwable;

abstract class BaseException extends ServerException
{

    public function __construct(int $code = 0, string $message = null, Throwable $previous = null)
    {
        parent::__construct($message ?? ResponseCode::getMessage($code), $code, $previous);
    }

    abstract function responseCode(): int;

}
