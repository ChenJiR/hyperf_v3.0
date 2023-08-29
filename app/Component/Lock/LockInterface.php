<?php
declare(strict_types=1);

namespace App\Component\Lock;


interface LockInterface
{

    //锁默认失效时间
    const LOCK_DEFAULT_TIMEOUT = 30;


    public function lock(string $key, mixed $value = '1', int $ttl = self::LOCK_DEFAULT_TIMEOUT): bool;

    public function unlock(string $key): bool;

    public function islock(string $key): bool;

}