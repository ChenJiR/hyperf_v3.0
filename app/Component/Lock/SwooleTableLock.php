<?php
declare(strict_types=1);

namespace App\Component\Lock;

use App\Component\Cache\SwooleTableCache;
use Hyperf\Di\Annotation\Inject;


class SwooleTableLock implements LockInterface
{
    #[Inject]
    protected SwooleTableCache $swooleTableCache;

    public function lock(string $key, mixed $value = '1', int $ttl = self::LOCK_DEFAULT_TIMEOUT): bool
    {
        return $this->swooleTableCache->lock($key, $value, $ttl);
    }

    public function unlock(string $key): bool
    {
        return $this->swooleTableCache->unlock($key);
    }

    public function islock(string $key): bool
    {
        return $this->swooleTableCache->islock($key);
    }
}