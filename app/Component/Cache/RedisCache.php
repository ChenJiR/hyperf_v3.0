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
namespace App\Component\Cache;

use Closure;
use Hyperf\Context\ApplicationContext;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Redis\RedisFactory;
use Hyperf\Redis\RedisProxy;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Redis;

use function array_chunk;
use function array_map;
use function array_shift;
use function array_values;
use function gettype;
use function is_array;
use function json_encode;
use function serialize;
use function strlen;
use function substr;

/**
 * Class RedisCache.
 */
class RedisCache implements CacheInterface
{
    public const CACHE_NAMESPACE = 'sw_news:';

    public const CACHE_PREFIX = '';

    public const PARKTYPE_DEFAULT = 0;

    public const PARKTYPE_JSON = 1;

    public const PARKTYPE_PHP_SERIALIZE = 2;

    protected RedisProxy $redis;

    private int $park_type = self::PARKTYPE_DEFAULT;

    private ?string $with_namespace = null;

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __construct(string $poolName = 'default', int $park_type = self::PARKTYPE_DEFAULT, ?string $with_namespace = null)
    {
        $this->redis = ApplicationContext::getContainer()->get(RedisFactory::class)->get($poolName);
        $this->park_type = $park_type;
        $this->with_namespace = $with_namespace;
    }

    public function generateKey($key): string
    {
        return ($this->with_namespace ?? self::CACHE_NAMESPACE) . self::CACHE_PREFIX . strval($key);
    }

    public function unGenerateKey(string $key): string
    {
        return substr($key, strlen(($this->with_namespace ?? self::CACHE_NAMESPACE) . self::CACHE_PREFIX));
    }

    public function serializeValue(mixed $value, ?int $ahead_time = null): string
    {
        switch ($this->park_type) {
            default:
            case self::PARKTYPE_DEFAULT:
                $type = gettype($value);
                $content = match ($type) {
                    'array' => $value,
                    'object' => serialize($value),
                    'NULL' => null,
                    default => strval($value),
                };
                $content = json_encode(['origindata_type' => $type, 'content' => $content, 'ahead_time' => $ahead_time]);
                break;
            case self::PARKTYPE_JSON:
                $content = json_encode($value);
                break;
            case self::PARKTYPE_PHP_SERIALIZE:
                $type = 'object';
                $content = json_encode(['origindata_type' => $type, 'content' => serialize($value), 'ahead_time' => $ahead_time]);
                break;
        }
        return $content;
    }

    /**
     * @param null|string $value
     */
    public function unSerializeValue(mixed $value): mixed
    {
        if ($value === false) {
            return null;
        }
        if (is_null($value)) {
            return null;
        }
        if (is_numeric($value)) {
            return $value;
        }

        $json_value = json_decode($value, true);
        if (! $json_value) {
            return $value;
        }
        if (! isset($json_value['origindata_type'])) {
            return $json_value;
        }

        return match ($json_value['origindata_type']) {
            default => $json_value['content'] ?? null,
            'object' => unserialize($json_value['content']),
            'integer' => intval($json_value['content']),
            'double' => doubleval($json_value['content']),
            'boolean' => boolval($json_value['content']),
            'NULL' => null,
        };
    }

    /**
     * 存入缓存,若 ttl<0 则无过期时间（不推荐）
     * demo:
     * RedisCache::instance()->set( 'key' , 'value' , 'ttl' );.
     * @param null $ttl
     */
    public function set(string $key, $value, $ttl = null): bool
    {
        $ttl = $ttl ?? self::CACHE_DEFALUT_TIMEOUT;
        return $this->redis->set(
            $this->generateKey($key),
            $this->serializeValue($value),
            $ttl > 0 ? $ttl : null
        );
    }

    /**
     * 存入缓存并以毫秒为单位设置 key 的生存时间
     * demo:
     * RedisCache::instance()->psetex( 'key' , 'value' , 'ttl' );.
     */
    public function psetex($key, $value, int $ttl = 1000): bool
    {
        return $this->redis->psetex($this->generateKey($key), intval($ttl), $this->serializeValue($value));
    }

    /**
     * 存入缓存,若 ttl<=0 则无过期时间（不推荐）
     * 若key已存在，则返回false
     * demo:
     * RedisCache::instance()->setnx( 'key' , 'value' , 'ttl' );.
     */
    public function setnx(string $key, mixed $value, int $ttl = self::CACHE_DEFALUT_TIMEOUT): bool
    {
        return $this->redis->set(
            $this->generateKey($key),
            $this->serializeValue($value),
            $ttl > 0 ? ['nx', 'ex' => $ttl] : ['nx']
        );
    }

    /**
     * 获取缓存内容，若没有缓存或缓存内容为null，则返回default的值 （default也可以传入匿名函数）
     * demo:
     * RedisCache::instance()->get( 'key' );
     * or
     * RedisCache::instance()->get( 'key' , 'default' );.
     * @param null|Closure|mixed $default
     */
    public function get($key, $default = null): mixed
    {
        $key = $this->generateKey($key);
        return $this->unSerializeValue($this->redis->get($key)) ?? ($default instanceof Closure ? $default() : $default);
    }

    /**
     * 获取缓存内容（可进行预获取更新缓存）
     * 打包方式为 PARKTYPE_JSON 的，无法预获取更新
     * demo:
     * function getContent(){ return mt_rand(0,10); }.
     *
     * RedisCache::instance()->set('test', getContent(), 10);
     *
     * RedisCache::instance()->getAheadCache('test', 10, 5, function(){ return getContent(); }); // 4
     *
     * sleep(4);
     *
     * RedisCache::instance()->getAheadCache('test', 10, 5, function(){ return getContent(); }); // 4
     *
     * sleep(1);
     *
     * RedisCache::instance()->getAheadCache('test', 10, 5, function(){ return getContent(); }); // 4
     *
     * sleep(1);
     *
     * RedisCache::instance()->getAheadCache('test', 10, 5, function(){ return getContent(); }); // 2
     */
    public function getAheadCache(string $key, int $ttl, int $ahead_ttl, Closure $get_cache_content, bool $cache_empty = false): mixed
    {
        $key = self::generateKey($key);

        $now = time();
        $new_cache_func = function () use ($key, $ttl, $ahead_ttl, $get_cache_content, $cache_empty, $now) {
            $new_res = $get_cache_content();
            if (! empty($new_res) || $cache_empty === true) {
                $this->redis->set($key, $this->serializeValue($new_res, $now + $ahead_ttl), $ttl > 0 ? $ttl : null);
            }
            return $new_res;
        };

        $result = $this->redis->get($key);
        $res = $this->unSerializeValue($result);
        if (empty($res)) {
            $res = $new_cache_func();
        }
        if ($this->getAheadTime($result) < $now) {
            Coroutine::defer($new_cache_func);
        }

        return $res;
    }

    /**
     * 将key中存入的值 +1 若没有key，则默认赋值 $default_num
     * 正常情况下返回存入的值
     * 注意：ttl每次操作时都会更新.
     *
     * demo:
     *
     *   $a = RedisCache::instance()->incr('test');  // $a = 1
     *   $a = RedisCache::instance()->incr('test');  // $a = 2
     *
     *   $a = RedisCache::instance()->incr('test2',0,-1); // $a = -1
     *   $a = RedisCache::instance()->incr('test2'); // $a = 0
     */
    public function incr(string $key, ?int $ttl = null, int $default_num = 1): bool|int
    {
        $key = $this->generateKey($key);
        if ($default_num != 1 && ! $this->redis->exists($key)) {
            $ret = $this->redis->set($key, $default_num, $ttl);
            return $ret ? $default_num : false;
        }
        $ret = $this->redis->incr($key);
        $ttl > 0 && $this->redis->expire($key, $ttl);
        return $ret;
    }

    /**
     * 将key中存入的值 -1 若没有key，则默认赋值 $default_num
     * 正常情况下返回存入的值
     * 注意：ttl每次操作时都会更新.
     *
     * demo:
     *
     *   $a = RedisCache::instance()->decr('test');  // $a = -1
     *   $a = RedisCache::instance()->decr('test');  // $a = -2
     *
     *   $a = RedisCache::instance()->decr('test2',0,1); // $a = 1
     *   $a = RedisCache::instance()->decr('test2'); // $a = 0
     */
    public function decr(string $key, ?int $ttl = null, int $default_num = -1): bool|int
    {
        $key = $this->generateKey($key);
        if ($default_num != -1 && ! $this->redis->exists($key)) {
            $ret = $this->redis->set($key, $default_num, $ttl);
            return $ret ? $default_num : false;
        }
        $ret = $this->redis->decr($key);
        $ttl > 0 && $this->redis->expire($key, $ttl);
        return $ret;
    }

    /**
     * 将key中存入的值 +$incr_amount 若没有key，则默认赋值 $default_num
     * 正常情况下返回存入的值
     * 注意：ttl每次操作时都会更新.
     */
    public function incrBy(string $key, ?int $ttl = null, int $incr_amount = 1, int $default_num = 1): bool|int
    {
        $key = $this->generateKey($key);
        if ($default_num != 1 && ! $this->redis->exists($key)) {
            $ret = $this->redis->set($key, $default_num, $ttl);
            return $ret ? $default_num : false;
        }
        $ret = $this->redis->incrby($key, $incr_amount);
        $ttl > 0 && $this->redis->expire($key, $ttl);
        return $ret;
    }

    /**
     * 将key中存入的值 -$decr_amount 若没有key，则默认赋值 $default_num
     * 正常情况下返回存入的值
     * 注意：ttl每次操作时都会更新.
     */
    public function decrBy(string $key, ?int $ttl = null, int $decr_amount = 1, int $default_num = -1): bool|int
    {
        $key = $this->generateKey($key);
        if ($default_num != -1 && ! $this->redis->exists($key)) {
            $ret = $this->redis->set($key, $default_num, $ttl);
            return $ret ? $default_num : false;
        }
        $ret = $this->redis->decrby($key, $decr_amount);
        $ttl > 0 && $this->redis->expire($key, $ttl);
        return $ret;
    }

    /**
     * 新内容替换原值，并返回原值
     * demo:
     * RedisCache::instance()->getset( 'key' , 'value' , 'ttl' );.
     */
    public function getset(string $key, mixed $value, int $ttl = self::CACHE_DEFALUT_TIMEOUT): mixed
    {
        $key = $this->generateKey($key);
        $value = $this->unSerializeValue($this->redis->getSet(
            $key,
            $this->serializeValue($value)
        ));
        $ttl > 0 && $this->redis->expire($key, $ttl);
        return $value;
    }

    /**
     * 批量删除多个key
     * demo:
     * RedisCache::instance()->deleteMultiple( ['key1','key2','key3'] );.
     * @param string[] $keys
     */
    public function deleteMultiple($keys): bool
    {
        $del_key = [];
        foreach ($keys as $item) {
            is_array($item)
                ? $this->deleteMultiple($item)
                : $del_key[] = $this->generateKey(strval($item));
        }
        return $this->redis->del($del_key) > 0;
    }

    /**
     * 删除缓存
     * demo:
     * RedisCache::instance()->delete( 'key1' );.
     */
    public function delete($key, bool $with_namespace = true): bool
    {
        return $this->redis->del($with_namespace ? $this->generateKey($key) : $key) > 0;
    }

    /**
     * 根据字符串匹配key并删除
     * demo:
     * RedisCache::instance()->setMultiple(
     *    [ 'user_1' => 'value1' , 'user_2' => 'value2' , 'user_3' => 'value3' , 'u_123' => 'value4'  ]
     * );
     * RedisCache::instance()->deleteByPattern( 'user*' );
     * RedisCache::instance()->get( 'user_1' ); //null
     * RedisCache::instance()->get( 'user_2' ); //null
     * RedisCache::instance()->get( 'user_3' ); //null
     * RedisCache::instance()->get( 'u_123' );  //value4.
     */
    public function deleteByPattern(string $pattern): bool
    {
        foreach (array_chunk($this->keys($pattern), 400) as $item) {
            $this->redis->del(...$item);
        }
        return true;
    }

    /**
     * 匹配缓存key
     * RedisCache::instance()->setMultiple(
     *    [ 'user_1' => 'value1' , 'user_2' => 'value2' , 'user_3' => 'value3' , 'u_123' => 'value4'  ]
     * );
     * RedisCache::instance()->keys('user_*'); // ['user_1','user_2','user_3'].
     */
    public function keys(string $pattern, bool $with_namespace = false): array
    {
        $keys = $this->redis->keys($this->generateKey($pattern));
        if ($with_namespace === false) {
            $ns_len = strlen($this->with_namespace ?? self::CACHE_NAMESPACE);
            $keys = array_map(
                function ($value) use ($ns_len) {
                    return substr($value, $ns_len);
                },
                $keys
            );
        }
        return $keys;
    }

    // 目前禁用
    public function clear(): bool
    {
        return false;
    }

    /**
     * 批量获取缓存内容
     * demo:
     * RedisCache::instance()->setMultiple(
     *    [ 'key1' => 'value1' , 'key2' => 'value2' , 'key3' => 'value3'  ]
     * );.
     *
     * // [ 'key1' => 'value1' , 'key2' => 'value2' , 'key3' => 'value3'  ]
     * RedisCache::instance()->getMultiple( [ 'key1' , 'key2' , 'key3' ] );
     *
     * @param null $default
     */
    public function getMultiple($keys, $default = null, bool $with_namespace = false): array
    {
        if (empty($keys)) {
            return [];
        }

        $keys = array_values($keys);
        $redis_pipeline = $this->redis->multi(Redis::PIPELINE);
        foreach ($keys as $item) {
            $redis_pipeline->get($this->generateKey($item));
        }
        $result = [];
        foreach ($redis_pipeline->exec() as $n => $item) {
            $key = $with_namespace ? $this->generateKey($keys[$n]) : strval($keys[$n]);
            $result[$key] = $this->unSerializeValue($item) ?? $default;
        }
        return $result;
    }

    /**
     * 通配符批量获取缓存内容
     * demo:
     * RedisCache::instance()->setMultiple(
     *    [ 'key1' => 'value1' , 'key2' => 'value2' , 'key3' => 'value3'  ]
     * );.
     *
     * // [ 'key1' => 'value1' , 'key2' => 'value2' , 'key3' => 'value3'  ]
     * RedisCache::instance()->getMultipleByPattern( 'key*' );
     *
     * @param null $default
     */
    public function getMultipleByPattern(string $pattern, $default = null, bool $with_namespace = false): array
    {
        $keys = $this->redis->keys($this->generateKey($pattern));
        $redis_pipeline = $this->redis->multi(Redis::PIPELINE);
        foreach ($keys as $item) {
            $redis_pipeline->get($this->generateKey($item));
        }
        $result = [];
        foreach ($redis_pipeline as $n => $item) {
            $key = $with_namespace ? strval($keys[$n]) : $this->unGenerateKey(strval($keys[$n]));
            $result[$key] = $this->unSerializeValue($item) ?? $default;
        }
        return $result;
    }

    /**
     * 批量设置缓存内容
     * demo:
     * RedisCache::instance()->setMultiple(
     *    [ 'key1' => 'value1' , 'key2' => 'value2' , 'key3' => 'value3'  ]
     * );.
     *
     * // [ 'key1' => 'value1' , 'key2' => 'value2' , 'key3' => 'value3'  ]
     * RedisCache::instance()->getMultiple( [ 'key1' , 'key2' , 'key3' ] );
     *
     * @param null $ttl
     */
    public function setMultiple($values, $ttl = null): bool
    {
        if (empty($values)) {
            return true;
        }

        $ttl = $ttl ?? self::CACHE_DEFALUT_TIMEOUT;
        $redis_pipeline = $this->redis->multi(Redis::PIPELINE);
        foreach ($values as $key => $value) {
            $redis_pipeline->set($this->generateKey($key), $this->serializeValue($value), $ttl);
        }
        $redis_pipeline->exec();
        return true;
    }

    /**
     * 检测是否有key
     * RedisCache::instance()->setMultiple(
     *    [ 'key1' => 'value1' , 'key2' => 'value2'  ]
     * );.
     *
     * RedisCache::instance()->has('key1'); //'1'
     * RedisCache::instance()->has('key2'); //'1'
     * RedisCache::instance()->has('key3'); //'0'
     */
    public function has(string $key): bool
    {
        return boolval($this->redis->exists($this->generateKey($key)));
    }

    /**
     * 重命名缓存key
     * RedisCache::instance()->set('srcKey','value');
     * RedisCache::instance()->rename('srcKey','dstKey');
     * RedisCache::instance()->get('srcKey'); //null
     * RedisCache::instance()->get('dstKey'); //'value'.
     *
     * force:强制更名,若更换后的key已经存在，则强制更名，更换后的key将被替换
     * RedisCache::instance()->setMultiple(
     *    [ 'srcKey' => 'value1' , 'dstKey' => 'value2'  ]
     * );
     * RedisCache::instance()->rename('srcKey','dstKey'); //false
     * RedisCache::instance()->get('srcKey'); //value1
     * RedisCache::instance()->get('dstKey'); //value2
     * RedisCache::instance()->rename('srcKey','dstKey',true); //true
     * RedisCache::instance()->get('srcKey'); //null
     * RedisCache::instance()->get('dstKey'); //value1
     */
    public function rename(string $srcKey, string $dstKey, bool $force = false): bool
    {
        return $force
            ? $this->redis->rename($this->generateKey($srcKey), $this->generateKey($dstKey))
            : $this->redis->renameNx($this->generateKey($srcKey), $this->generateKey($dstKey));
    }

    /**
     * 检查是否上锁
     */
    public function isLock(string $key): bool
    {
        return $this->has($this->generateKey($key));
    }

    /**
     * 锁
     */
    public function lock(string $key, mixed $value = '1', int $ttl = self::LOCK_DEFAULT_TIMEOUT): bool
    {
        return $this->setnx($key, $value, $ttl);
    }

    public function unlock(string $key): bool
    {
        return $this->delete($key);
    }

    /**
     * 设置一个hash值
     */
    public function setHashMap(string $key, array $values, int $ttl = self::CACHE_DEFALUT_TIMEOUT, bool $pack = false): bool
    {
        if (empty($values)) {
            return true;
        }
        if ($pack) {
            foreach ($values as &$item) {
                $item = $this->serializeValue($item);
            }
        }
        $res = $this->redis->hMSet($this->generateKey($key), $values);
        if ($res) {
            $this->redis->expire($this->generateKey($key), $ttl);
            return true;
        }
        return false;
    }

    /**
     * 获取全部hashkey的值
     */
    public function getHashMap(string $key, mixed $default = null, bool $unpack = false): mixed
    {
        $res = $this->redis->hGetAll($this->generateKey($key));
        if (empty($res)) {
            return $default instanceof Closure ? $default() : $default;
        }
        return $unpack
            ? array_map(
                function ($value) {
                    return $this->unSerializeValue($value);
                },
                $res
            )
            : $res;
    }

    public function delHashValue(string $key, array $fields): int
    {
        return $this->redis->hDel($this->generateKey($key), ...$fields);
    }

    /**
     * 获取hash值
     */
    public function getHashValue(string $key, array|string $hash_keys, mixed $default = null, bool $unpack = false): mixed
    {
        if (empty($hash_keys)) {
            return $default instanceof Closure ? $default() : $default;
        }
        $res = $this->redis->hMGet($this->generateKey($key), is_array($hash_keys) ? $hash_keys : [$hash_keys]);
        if (empty($res)) {
            return $default instanceof Closure ? $default() : $default;
        }
        if (is_array($hash_keys)) {
            $result = [];
            foreach ($hash_keys as $key) {
                $result[$key] = $unpack ? $this->unSerializeValue(array_shift($res)) : array_shift($res);
            }
            $res = $result;
        } else {
            $res = isset($res[0]) ? ($unpack ? $this->unSerializeValue($res[0]) : $res[0]) : $res[$hash_keys];
        }
        return $res ?? ($default instanceof Closure ? $default() : $default);
    }

    /**
     * 设置hash值
     */
    public function setHashValue(string $key, string $hash_keys, $value, bool $pack = false): bool|int
    {
        return $this->redis->hSet($this->generateKey($key), $hash_keys, $pack ? $this->serializeValue($value) : $value);
    }

    public function expire(string $key, int $ttl = self::CACHE_DEFALUT_TIMEOUT): bool
    {
        return $this->redis->expire($this->generateKey($key), $ttl);
    }

    public function getRedisClient(): RedisProxy
    {
        return $this->redis;
    }

    private function getAheadTime(mixed $value): int
    {
        if ($value === false) {
            return PHP_INT_MAX;
        }
        if (is_null($value)) {
            return PHP_INT_MAX;
        }
        if (is_numeric($value)) {
            return PHP_INT_MAX;
        }

        $json_value = json_decode($value, true);
        if (! $json_value || ! isset($json_value['ahead_time'])) {
            return PHP_INT_MAX;
        }

        return intval($json_value['ahead_time']);
    }
}
