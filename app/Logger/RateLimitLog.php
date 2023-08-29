<?php


namespace App\Logger;


use App\Component\Cache\RedisCache;
use App\Component\RateLimiter\KeyTypeEnum;
use App\Middleware\RateLimitMiddleware;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Context\ApplicationContext;
use Psr\Log\LoggerInterface;
use function Hyperf\Support\make;

class RateLimitLog
{

    public static function get(string $api): LoggerInterface
    {
        return ApplicationContext::getContainer()->get(LoggerFactory::class)->get($api, 'rateLimit');
    }

    public static function log(string $api, string $key, array $rule)
    {
        $redis = make(RedisCache::class);
        $lock_key = 'rl_log_lock' . md5("$api-$key");
        if (!$redis->lock($lock_key, '1', 60)) {
            return;
        }
        $key_type = is_array($rule['key_type']) ? $rule['key_type'] : [$rule['key_type']];
        $key_type_desc = [];
        foreach ($key_type as $item) {
            $key_type_desc[] = KeyTypeEnum::getMessage($item);
        }
        $content = [
            '每秒生成令牌数' => $rule['create'] ?? '未定义，默认' . RateLimitMiddleware::DEFAULT_CREATE,
            '令牌桶容量' => $rule['capacity'] ?? '未定义，默认' . RateLimitMiddleware::DEFAULT_CAPACITY,
            '请求消耗令牌数' => $rule['consume'] ?? '未定义，默认' . RateLimitMiddleware::DEFAULT_CONSUME,
            '限流维度' => implode('+', $key_type_desc),
            'key' => $key
        ];
        self::get($api)->info($api, $content);
    }

}