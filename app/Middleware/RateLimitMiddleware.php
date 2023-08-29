<?php


namespace App\Middleware;


use App\Component\Cache\RedisCache;
use App\Component\Notice\NoticeInterface;
use App\Component\RateLimiter\KeyTypeEnum;
use App\Component\RateLimiter\RateLimiter;
use App\Logger\RateLimitLog;
use App\Util\CommonHelper;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\RateLimit\Exception\RateLimitException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use function Hyperf\Support\make;

class RateLimitMiddleware implements MiddlewareInterface
{

    const RATE_LIMIT_RULE_REDISKEY = 'rateLimiter:rule';

    const DEFAULT_CREATE = 10;
    const DEFAULT_CAPACITY = 10;
    const DEFAULT_CONSUME = 1;
    const DEFAULT_WAIT = false;
    const DEFAULT_WAITTIMEOUT = 1;

    #[Inject]
    protected RequestInterface $request;

    #[Inject]
    protected HttpResponse $response;

    #[Inject]
    protected NoticeInterface $notice;

    protected static array $ratelimit_rule = [];

    public function __construct()
    {
        self::setRateLimitRule();
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $url = trim($request->getUri()->getPath(), '/');
        if (isset(self::$ratelimit_rule[$url]) && !CommonHelper::arrayValueIsEmpty(self::$ratelimit_rule[$url], 'key_type')) {
            $rule = self::$ratelimit_rule[$url];
            $key_type = is_array($rule['key_type']) ? $rule['key_type'] : [$rule['key_type']];
            $key = [];
            foreach ($key_type as $item) {
                $each_key = match (intval($item)) {
                    KeyTypeEnum::RATELIMIT_KEYTYPE_IP => CommonHelper::getClientIp(),
                    KeyTypeEnum::RATELIMIT_KEYTYPE_TOKEN => $this->request->all()['token'] ?? '',
                    KeyTypeEnum::RATELIMIT_KEYTYPE_DEVICE => 'xxx',
                    KeyTypeEnum::RATELIMIT_KEYTYPE_PHONEMODEL => $this->request->all()['phoneModel'] ?? '',
                    default => $url
                };
                if (empty($each_key)) continue;
                $key[] = $each_key;
            }
            // 未收集到客户端特征，跳过限流
            if (empty($key)) return $handler->handle($request);

            $key = implode('|', $key) ?: $url;

            try {
                RateLimiter::run(
                    intval($rule['create'] ?? self::DEFAULT_CREATE),
                    intval($rule['capacity'] ?? self::DEFAULT_CAPACITY),
                    intval($rule['consume'] ?? self::DEFAULT_CONSUME),
                    boolval($rule['wait'] ?? self::DEFAULT_WAIT),
                    intval($rule['waitTimeout'] ?? self::DEFAULT_WAITTIMEOUT),
                    $key
                );
            } catch (RateLimitException $e) {
//                $this->sendNotice($url, $key, $rule);
                RateLimitLog::log($url, $key, $rule);
                if (isset($rule['response'])) {
                    return $this->response->json($rule['response']);
                } else {
                    throw $e;
                }
            }
        }

        return $handler->handle($request);
    }

    public static function setRateLimitRule()
    {
        self::$ratelimit_rule = make(RedisCache::class)->getHashMap(self::RATE_LIMIT_RULE_REDISKEY, [], true);
    }

    public static function getRateLimitRule()
    {
        return self::$ratelimit_rule;
    }

    public static function editRateLimitRule(
        string $url,
        int    $create = self::DEFAULT_CREATE,
        int    $capacity = self::DEFAULT_CAPACITY,
        int    $consume = self::DEFAULT_CONSUME,
        array  $key_type = [KeyTypeEnum::RATELIMIT_KEYTYPE_IP],
        bool   $wait = self::DEFAULT_WAIT,
        int    $waitTimeout = self::DEFAULT_WAITTIMEOUT
    )
    {
        make(RedisCache::class)->setHashValue(
            self::RATE_LIMIT_RULE_REDISKEY, trim($url, '/'),
            ['create' => $create, 'capacity' => $capacity, 'consume' => $consume, 'wait' => $wait, 'waitTimeout' => $waitTimeout, 'key_type' => $key_type],
            true
        );
    }

    public static function deleteRateLimitRule(string $url)
    {
        make(RedisCache::class)->delHashValue(self::RATE_LIMIT_RULE_REDISKEY, [$url]);
    }

    private function sendNotice(string $url, string $key, array $rule)
    {
        $redis = make(RedisCache::class);
        $lock_key = 'rl_notice_lock' . md5("$url-$key");
        if (!$redis->lock($lock_key, '1', 60)) {
            return;
        }
        $key_type = is_array($rule['key_type']) ? $rule['key_type'] : [$rule['key_type']];
        $key_type_desc = [];
        foreach ($key_type as $item) {
            $key_type_desc[] = KeyTypeEnum::getMessage($item);
        }
        $this->notice->cardMsg(
            NoticeInterface::CARD_MSG_INFO,
            '已触发限流api：' . $url,
            $key . '已触发限流，1分钟内此规则不会再发送消息',
            [
                '每秒生成令牌数' => $rule['create'] ?? '未定义，默认' . self::DEFAULT_CREATE,
                '令牌桶容量' => $rule['capacity'] ?? '未定义，默认' . self::DEFAULT_CAPACITY,
                '请求消耗令牌数' => $rule['consume'] ?? '未定义，默认' . self::DEFAULT_CONSUME,
                '限流维度' => implode('+', $key_type_desc),
                'key' => $key
            ]
        );
    }

}