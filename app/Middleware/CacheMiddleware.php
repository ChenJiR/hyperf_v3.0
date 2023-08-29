<?php

namespace App\Middleware;

use App\Component\Cache\CacheInterface;
use App\Component\Cache\RedisCache;
use App\Component\Cache\SwooleTableCache;
use Closure;
use Exception;
use Hyperf\Config\Annotation\Value;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\Context\ApplicationContext;
use Hyperf\Codec\Json;
use Hyperf\Coroutine\Coroutine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Swoole\Table;
use function Hyperf\Support\make;
use function Hyperf\Support\env;

class CacheMiddleware implements MiddlewareInterface
{

    const CHECK_REDIS_DRIVER_COUNT = 150;

    const CACHE_NAMESPACE = 'cache_mdw:';

    #[Inject]
    protected HttpResponse $response;

    #[Inject]
    protected RequestInterface $request;

    #[Inject]
    protected SwooleTableCache $swooleTableCache;

    protected RedisCache $redisCache;

    #[Value("apicache")]
    private array $cache_config;

    public function __construct()
    {
        $this->redisCache = make(RedisCache::class, ['cache']);
    }

    /**
     * @throws Exception
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 本地环境为了方便调试不走缓存中间件
        if (env('APP_ENV', 'prod') == 'local') {
            return $handler->handle($request);
        }

        $method = $this->request->getMethod();
        $inputdata = $this->request->all();
        $url = $this->request->path();

        if ($this->skip($url, $method, $inputdata)) {
            return $handler->handle($request);
        }

        //持续访问大于 self::CHECK_REDIS_DRIVER_COUNT 的接口会使用swooletable缓存，速度更快
        //当持续20秒无人访问，则降级为redis缓存
        //暂时不启用swooletable缓存
//        $request_count = $this->requestCounter($url, $method, $inputdata);
//        $cache_driver = intval($request_count) > self::CHECK_REDIS_DRIVER_COUNT ? $this->swooleTableCache : $this->redisCache;

        $cache_driver = $this->redisCache;

        $cache_key = $this->cacheKey($url, $method, $inputdata);
        $data = $this->getCache($cache_driver, $cache_key);

        if (empty($data)) {
            // api是否被锁
            $islock = false;
            while ($this->swooleTableCache->isLock($cache_key)) {
                $islock = true;
                Coroutine::sleep(0.15);
            }
            // 若已被锁，解开锁的时候，api内容应该已经缓存，重新获取缓存
            if ($islock) {
                return $this->process($request, $handler);
            }
            $this->swooleTableCache->lock($cache_key, '1', 4);
            try {
                $response = $handler->handle($request);
                $data = $response->getBody()->getContents();
                $content_type = $response->getHeaderLine('content-type') ?: $response->getHeaderLine('Content-Type');
                try {
                    $response_data = match ($content_type) {
                        default => Json::decode($data),
                        'text/plain', 'application/octet-stream' => $data
                    };
                } catch (Exception) {
                    $response_data = $data;
                }
                //返回正确才存入缓存
                if ($this->canCache($url, $method, $inputdata, $response_data)) {
                    $ttl = $this->cacheTtl($url, $method, $inputdata, $response_data);
                    $this->setCache($cache_driver, $cache_key, $data, $ttl);
                }
                $this->swooleTableCache->unlock($cache_key);
            } catch (Exception $e) {
                $this->swooleTableCache->unlock($cache_key);
                throw $e;
            }
            return $response;
        }
        return $this->response
            ->withAddedHeader('content-type', 'application/json; charset=utf-8')
            ->withBody(new SwooleStream($data));
    }

    private function skip(string $url, string $method, array $inputdata = []): bool
    {
        if (isset($this->cache_config[$url])) {
            $skip = $this->cache_config[$url]['skip'] ?? null;
            if ($skip instanceof Closure || is_callable($skip)) {
                return boolval($skip($method, $inputdata, ApplicationContext::getContainer()));
            } else if (is_bool($skip)) {
                return $skip;
            }
        }
        return false;
    }

    private function cacheKey(string $url, string $method, array $inputdata = []): string
    {
        if (isset($this->cache_config[$url])) {
            $cacheKey = $this->cache_config[$url]['cacheKey'] ?? null;
            if ($cacheKey instanceof Closure || is_callable($cacheKey)) {
                return self::CACHE_NAMESPACE . strval($cacheKey($method, $inputdata, ApplicationContext::getContainer()));
            } else if (is_string($cacheKey)) {
                return self::CACHE_NAMESPACE . $cacheKey;
            }
        }
        $inputkeydata = $inputdata;
        unset($inputkeydata['token']);
        unset($inputkeydata['timestamp']);
        unset($inputkeydata['sign']);
        unset($inputkeydata['phoneModel']);
        unset($inputkeydata['platformPublic']);
        unset($inputkeydata['systemOS']);
        unset($inputkeydata['deviceId']);
        unset($inputkeydata['appCurrentVersion']);
        unset($inputkeydata['app_id']);
        $inputkey = md5(json_encode($inputkeydata));
        return self::CACHE_NAMESPACE . "$method:$url:$inputkey";
    }

    private function cacheTtl(string $url, string $method, array $inputdata = [], mixed $responsedata = []): int
    {
        if (isset($this->cache_config[$url])) {
            $cacheTtl = $this->cache_config[$url]['cacheTtl'] ?? null;
            if ($cacheTtl instanceof Closure || is_callable($cacheTtl)) {
                return intval($cacheTtl($method, $inputdata, $responsedata, ApplicationContext::getContainer()));
            } else if (is_int($cacheTtl)) {
                return $cacheTtl;
            }
        }
        return 10;
    }

    private function canCache(string $url, string $method, array $inputdata = [], mixed $responsedata = []): bool
    {
        if (isset($this->cache_config[$url])) {
            $canCache = $this->cache_config[$url]['canCache'] ?? null;
            if ($canCache instanceof Closure || is_callable($canCache)) {
                return boolval($canCache($method, $inputdata, $responsedata, ApplicationContext::getContainer()));
            } else if (is_bool($canCache)) {
                return $canCache;
            }
        }
        return true;
    }

    protected function setCache(CacheInterface $cache_driver, string $cache_key, mixed $value, int $ttl)
    {
        $cache_driver->set($cache_key, $value, $ttl);
        $cache_driver instanceof SwooleTableCache && $this->redisCache->set($cache_key, $value, $ttl);
    }

    private function getCache(CacheInterface $cache_driver, $cache_key): ?string
    {
        if ($cache_driver instanceof SwooleTableCache) {
            return $cache_driver->get($cache_key, function () use ($cache_key) {
                return $this->getCache($this->redisCache, $cache_key);
            });
        } else if ($cache_driver instanceof RedisCache) {
            return $cache_driver->get($cache_key);
        } else {
            return null;
        }
    }

    /**
     * 请求计数器
     * @param string $url
     * @param string $method
     * @param array $inputdata
     * @param bool $reset
     * @return int|bool
     */
    private function requestCounter(string $url, string $method, array $inputdata, bool $reset = false): int|bool
    {
        $counter_cachekey = $this->cacheKey($url, $method, $inputdata);
        $this->swooleTableCache->getOrCreateTable($counter_cachekey, SwooleTableCache::SIZE, 64, Table::TYPE_INT);
        return $reset
            ? $this->swooleTableCache->delete($counter_cachekey)
            : $this->swooleTableCache->incr($counter_cachekey, 15);
    }

}