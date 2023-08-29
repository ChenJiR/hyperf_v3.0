<?php


namespace App\Logger;


use App\Constants\ResponseCode;
use App\Exception\BaseException;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Context\ApplicationContext;
use Psr\Log\LoggerInterface;
use Stringable;
use Throwable;

class Log
{

    public static function get(string $name = 'app'): LoggerInterface
    {
        return ApplicationContext::getContainer()->get(LoggerFactory::class)->get($name);
    }

    public static function exceptionError(Throwable $throwable, string $name = 'app'): void
    {
        $request = ApplicationContext::getContainer()->get(RequestInterface::class);

        $log_data = ['file' => $throwable->getFile(), 'line' => $throwable->getLine()];

        if ($throwable instanceof BaseException) {
            $log_data['headers'] = $request->getHeaders();
            $log_data['request'] = $request->all();
            self::get($name)->error($throwable->getMessage() ?? ResponseCode::getMessage($throwable->responseCode()), $log_data);
        } else {
            $log_data['trace'] = $throwable->getTrace();
            self::get($name)->error($throwable->getMessage(), $log_data);
        }
    }

    public static function error(string|Stringable $msg, array $content = [], string $name = 'app'): void
    {
        self::get($name)->error($msg, $content);
    }

    public static function warning(string|Stringable $msg, array $content = [], string $name = 'app'): void
    {
        self::get($name)->warning($msg, $content);
    }

    public static function info(string|Stringable $msg, array $content = [], string $name = 'app'): void
    {
        self::get($name)->info($msg, $content);
    }

    public static function debug(string|Stringable $msg, array $content = [], string $name = 'app'): void
    {
        self::get($name)->debug($msg, $content);
    }

    public static function alert(string|Stringable $msg, array $content = [], string $name = 'app'): void
    {
        self::get($name)->alert($msg, $content);
    }

    public static function notice(string|Stringable $msg, array $content = [], string $name = 'app'): void
    {
        self::get($name)->notice($msg, $content);
    }

    public static function critical(string|Stringable $msg, array $content = [], string $name = 'app'): void
    {
        self::get($name)->critical($msg, $content);
    }

    public static function emergency(string|Stringable $msg, array $content = [], string $name = 'app'): void
    {
        self::get($name)->emergency($msg, $content);
    }

}