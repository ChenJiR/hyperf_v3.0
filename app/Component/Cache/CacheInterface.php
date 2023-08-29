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

interface CacheInterface extends \Psr\SimpleCache\CacheInterface
{
    public const CACHE_DEFALUT_TIMEOUT = 60 * 60;

    public const LOCK_DEFAULT_TIMEOUT = 30;

    /**
     * 存入缓存,若已存在则返回false.
     */
    public function setnx(string $key, mixed $value, int $ttl = self::CACHE_DEFALUT_TIMEOUT): bool;

    /**
     * 替换缓存内容并返回原内容.
     */
    public function getset(string $key, mixed $value, int $ttl = self::CACHE_DEFALUT_TIMEOUT): mixed;

    /**
     * 重命名
     * 若已存在，则返回false
     * 当$force=true时，强制更改.
     * @param bool $force 是否强制更名
     */
    public function rename(string $srcKey, string $dstKey, bool $force = false): bool;

    public function incr(string $key, ?int $ttl = null, int $default_num = 1): bool|int;

    public function decr(string $key, ?int $ttl = null, int $default_num = 1): bool|int;

    public function incrBy(string $key, ?int $ttl = null, int $incr_amount = 1, int $default_num = 1): bool|int;

    public function decrBy(string $key, ?int $ttl = null, int $decr_amount = 1, int $default_num = -1): bool|int;

    public function getAheadCache(string $key, int $ttl, int $ahead_ttl, Closure $get_cache_content, bool $cache_empty = false): mixed;
}
