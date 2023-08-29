<?php
declare(strict_types=1);

namespace App\Component\Lock;

use App\Component\Cache\RedisCache;
use Hyperf\Di\Annotation\Inject;

/**
 * 适用于跨进程锁
 */
class RedisLock implements LockInterface
{
    #[Inject]
    protected RedisCache $redisCache;

    public function lock(string $key, mixed $value = '1', int $ttl = self::LOCK_DEFAULT_TIMEOUT): bool
    {
        return $this->redisCache->lock($key, $value, $ttl);
    }

    public function unlock(string $key): bool
    {
        return $this->redisCache->unlock($key);
    }

    public function islock(string $key): bool
    {
        return $this->redisCache->islock($key);
    }
}