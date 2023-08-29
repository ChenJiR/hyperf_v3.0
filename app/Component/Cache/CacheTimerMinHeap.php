<?php
declare(strict_types=1);

namespace App\Component\Cache;

use Exception;
use SplHeap;


/**
 * 缓存失效计时器
 * Class CacheTimerMinHeap
 * @package App\Component\Cache
 */
class CacheTimerMinHeap extends SplHeap
{

    private array $cacheTimerList = [];

    public function current(): mixed
    {
        $data = parent::current();
        while ($data['ttl'] < $this->cacheTimerList[$data['key']]) {
            parent::next();
            $data = parent::current();
        }
        return $data;
    }

    public function extract(): mixed
    {
        $data = parent::current();
        while ($data['ttl'] < $this->cacheTimerList[$data['key']]) {
            parent::next();
            $data = parent::current();
        }
        $data = parent::extract();
        unset($this->cacheTimerList[$data['key']]);
        return $data;
    }

    /**
     * @param int $ttl
     * @param string $key
     */
    public function setTimer(int $ttl, string $key)
    {
        $this->setTimerByTimestamp(time() + $ttl, $key);
    }

    /**
     * @param int $timestamp
     * @param string $key
     */
    public function setTimerByTimestamp(int $timestamp, string $key)
    {
        if (intval($this->cacheTimerList[$key] ?? 0) === $timestamp) {
            return;
        }
        if ($timestamp < time()) $timestamp = time() + 1;
        $this->cacheTimerList[$key] = $timestamp;
        $this->insert(['ttl' => $timestamp, 'key' => $key]);
    }

    protected function compare($value1, $value2): int
    {
        if ($value1['ttl'] === $value2['ttl']) return 0;
        return $value1['ttl'] < $value2['ttl'] ? 1 : -1;
    }

}
