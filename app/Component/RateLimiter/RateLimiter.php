<?php


namespace App\Component\RateLimiter;


use bandwidthThrottle\tokenBucket\Rate;
use bandwidthThrottle\tokenBucket\storage\StorageException;
use bandwidthThrottle\tokenBucket\TokenBucket;
use Closure;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\RateLimit\Exception\RateLimitException;
use Hyperf\RateLimit\Handler\RateLimitHandler;
use App\Component\RateLimiter\storage\RedisStorage;
use Hyperf\Redis\Redis;
use Hyperf\Context\ApplicationContext;
use Swoole\Coroutine;
use function Hyperf\Support\make;

class RateLimiter
{

    public static function run(
        int $create = 10,
        int $capacity = 10,
        int $consume = 1,
        bool $wait = false,
        int $waitTimeout = 1,
        callable|Closure|string|array|null $key = null,
        callable|Closure|array|null $limitCallback = null
    )
    {
        $container = ApplicationContext::getContainer();
        if (is_string($key)) {
            $bucketKey = $key;
        } else if ($key instanceof Closure || is_callable($key)) {
            $bucketKey = $key();
        }

        if (!isset($bucketKey) || !is_string($bucketKey)) {
            $bucketKey = $container->get(RequestInterface::class)->getUri()->getPath();
        }

        $storage = make(RedisStorage::class, ['key' => $bucketKey, 'redis' => $container->get(Redis::class), 'timeout' => $waitTimeout]);
        $rate = make(Rate::class, ['tokens' => $create, 'unit' => Rate::SECOND]);
        $bucket = make(TokenBucket::class, ['capacity' => $capacity, 'rate' => $rate, 'storage' => $storage]);
        $bucket->bootstrap($capacity);

        $maxTime = microtime(true) + $waitTimeout;
        $seconds = 0;

        if ($wait) {
            while (true) {
                try {
                    if ($bucket->consume($consume ?? 1, $seconds)) {
                        return true;
                    }
                } catch (StorageException) {
                }
                if (microtime(true) + $seconds > $maxTime) {
                    break;
                }
                Coroutine::sleep(max($seconds, 0.001));
            }
        } else {
            if ($bucket->consume($consume ?? 1, $seconds)) {
                return true;
            }
        }

        if ($limitCallback instanceof Closure) {
            return $limitCallback($seconds);
        } else if (is_callable($limitCallback)) {
            return call_user_func($limitCallback, $seconds);
        } else {
            throw new RateLimitException('Service Unavailable.', 503);
        }
    }
}