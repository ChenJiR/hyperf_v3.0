<?php
declare(strict_types=1);

namespace App\Component\Cache;

use Closure;
use Exception;
use Hyperf\Coroutine\Coroutine;
use Swoole\Table;
use function substr, strlen, serialize, gettype, json_encode, md5;

/**
 * Class RedisCache
 * @package App\Component\Cache
 *
 */
class SwooleTableCache implements CacheInterface
{
    const COMMON_TABLE_NAME = 'common_table';

    //最大行数
    const SIZE = 16384;
    const CONFLICT_PROPORTION = 0.3;

    //定时清空 调用次数小于5次的缓存
    const COLD_NUM = 5;

    /**
     * 计时器
     * @var CacheTimerMinHeap
     */
    private CacheTimerMinHeap $timeHeap;

    /**
     * @var Table[]
     */
    private array $cache_table = [];

    /**
     * @var int[]
     */
    private array $cache_table_contentsize = [];

    public function __construct()
    {
        //通用table
        $this->createTable(self::COMMON_TABLE_NAME);
        $this->createTable('lock', 1024, 64);
        $this->timeHeap = new CacheTimerMinHeap();
    }

    public function getSwooleTable(string $table_name): ?Table
    {
        return $this->cache_table[$table_name] ?? null;
    }

    public function getSwooleTableContentSize(string $table_name): ?int
    {
        return $this->cache_table_contentsize[$table_name] ?? null;
    }

    private function createTable(string $table_name, int $table_size = self::SIZE, int $content_size = 20480, int $content_type = Table::TYPE_STRING): Table
    {
        $table = new Table($table_size, self::CONFLICT_PROPORTION);
        $table->column('cache_key', Table::TYPE_STRING, 64);
        $table->column('content', $content_type, $content_size);
        $table->column('use_num', Table::TYPE_INT);
        $table->create();
        $this->cache_table[$table_name] = $table;
        $this->cache_table_contentsize[$table_name] = $content_size;
        return $table;
    }

    public function getTableName(string $key): string
    {
        $table_position = stripos($key, ':');
        return $table_position ? substr($key, 0, $table_position) : self::COMMON_TABLE_NAME;
    }

    public function getOrCreateTable(string $key, int $table_size = self::SIZE, int $content_size = 20480, int $content_type = Table::TYPE_STRING): Table
    {
        return $this->getOrCreateTableByTableName($this->getTableName($key), $table_size, $content_size, $content_type);
    }

    public function getOrCreateTableByTableName(string $table_name = self::COMMON_TABLE_NAME, int $table_size = self::SIZE, int $content_size = 20480, int $content_type = Table::TYPE_STRING): Table
    {
        return $this->getSwooleTable($table_name) ?? $this->createTable($table_name, $table_size, $content_size, $content_type);
    }

    public function checkContentLength(string $key, mixed $value): bool
    {
        return strlen($this->serializeValue($value)) > ($this->getSwooleTableContentSize($this->getTableName($key)) ?? 0);
    }

    /**
     * 清理过期缓存
     * @param int|null $time
     * @return array
     */
    public function clearDisableCache(?int $time = null): array
    {
        $time = $time ?? time();
        $keys = [];
        while ($this->timeHeap->valid() && $time >= $this->timeHeap->current()['ttl']) {
            $key = $this->timeHeap->extract()['key'];
            $table = $this->getSwooleTable($this->getTableName($key));
            isset($table) && $table->del($key);
            $keys[] = $key;
        }
        return $keys;
    }

    /**
     * 清理调用次数低的缓存
     * @return array
     */
    public function clearColdCache(): array
    {
        $keys = [];
        foreach ($this->cache_table as $table) {
            foreach ($table as $item) {
                if ($item['use_num'] < self::COLD_NUM) {
                    $keys[] = $item['cache_key'];
                    $table->del($item['cache_key']);
                }
            }
        }
        return $keys;
    }

    /**
     * 淘汰1个最近即将过期的缓存
     */
    public function obsoleteCache(int $num = 1): void
    {
        $time = 0;
        while ($time < $num) {
            $key = $this->timeHeap->valid() ? $this->timeHeap->extract()['key'] : null;
            $key && $this->delete($key);
            $time++;
        }
    }

    public static function generateKey($key): string
    {
        $key = trim(strval($key));
        if (strlen($key) > 63) {
            $table_position = stripos($key, ':');
            return $table_position ? substr($key, 0, $table_position + 1) . md5(substr($key, $table_position + 1)) : md5($key);
        } else {
            return $key;
        }
    }

    /**
     * @param mixed $value
     * @param int|null $ahead_time
     * @return string
     */
    private function serializeValue(mixed $value, ?int $ahead_time = null): string
    {
        $type = gettype($value);
        $content = match ($type) {
            'array' => $value,
            'object' => serialize($value),
            'NULL' => null,
            default => strval($value),
        };
        return json_encode(['origindata_type' => $type, 'content' => $content, 'ahead_time' => $ahead_time]);
    }

    /**
     * @param array $value
     * @return mixed
     */
    private function unSerializeValue(mixed $value): mixed
    {
        if ($value === false) return null;
        if (is_null($value)) return null;
        if (is_numeric($value)) return $value;

        $json_value = json_decode($value, true);
        if (!$json_value) return $value;
        if (!isset($json_value['origindata_type'])) return $json_value;

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
     * @param mixed $value
     * @return int
     */
    private function getAheadTime(mixed $value): int
    {
        if ($value === false) return PHP_INT_MAX;
        if (is_null($value)) return PHP_INT_MAX;
        if (is_numeric($value)) return PHP_INT_MAX;

        $json_value = json_decode($value, true);
        if (!$json_value || !isset($json_value['ahead_time'])) return PHP_INT_MAX;

        return intval($json_value['ahead_time']);
    }

    /**
     * 获取缓存内容，若没有缓存或缓存内容为null，则返回default的值 （default也可以传入匿名函数）
     * demo:
     * SwooleTableCache::instance()->get( 'key' );
     * or
     * SwooleTableCache::instance()->get( 'key' , 'default' );
     * @param $key
     * @param null|mixed|Closure $default
     * @return mixed
     */
    public function get($key, $default = null): mixed
    {
        $key = self::generateKey($key);
        $table = $this->getOrCreateTable($key);
        $result = $table->get($key, 'content');
        if ($result) {
            $table->incr($key, 'use_num');
            return $this->unSerializeValue($result) ?? ($default instanceof Closure ? $default() : $default);
        } else {
            return $default instanceof Closure ? $default() : $default;
        }
    }

    public function getAheadCache(string $key, int $ttl, int $ahead_ttl, Closure $get_cache_content, bool $cache_empty = false): mixed
    {
        $key = self::generateKey($key);
        $table = $this->getOrCreateTable($key);

        $new_cache_func = function () use ($key, $ttl, $ahead_ttl, $get_cache_content, $cache_empty) {
            $new_res = $get_cache_content();
            if (!empty($new_res) || $cache_empty === true) {
                $this->set($key, $new_res, $ttl, $ahead_ttl);
            }
            return $new_res;
        };

        $result = $table->get($key, 'content');
        $res = $this->unSerializeValue($result);
        if (empty($res)) {
            $res = $new_cache_func();
        }
        if ($this->getAheadTime($result) < time()) {
            Coroutine::defer($new_cache_func);
        }

        return $res;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @param int|null $ahead_ttl
     * @return bool
     */
    public function set(string $key, mixed $value, $ttl = self::CACHE_DEFALUT_TIMEOUT, ?int $ahead_ttl = null): bool
    {
        $key = self::generateKey($key);
        if (!$ttl || $ttl <= 0) $ttl = self::CACHE_DEFALUT_TIMEOUT;
        $table = $this->getOrCreateTable($key);
        $content = $this->serializeValue($value, is_numeric($ahead_ttl) ? (time() + $ahead_ttl) : null);
        if (strlen($content) > $this->getSwooleTableContentSize($this->getTableName($key))) {
            return false;
        }
        $already_data_use_num = $table->get($key, 'use_num');
        $data = ['cache_key' => $key, 'content' => $content, 'use_num' => $already_data_use_num ?: 0];
        while (!$table->set($key, $data)) {
            $this->obsoleteCache(2);
        }
        $this->setTimer($ttl, $key);
        return true;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function delete($key): bool
    {
        $key = self::generateKey($key);
        $table = $this->getSwooleTable($this->getTableName($key));
        isset($table) && $table->del($key);
        return true;
    }

    public function deleteTable(string $table_name)
    {
        $table = $this->getSwooleTable($this->getTableName($table_name));
        isset($table) && $table->destroy();
    }

    /**
     * @return bool
     */
    public function clear(): bool
    {
        foreach ($this->cache_table as $table) {
            $table->destroy();
        }
        return true;
    }

    public function getMultiple($keys, $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    /**
     * @param iterable $values
     * @param null $ttl
     * @return bool
     * @throws Exception
     */
    public function setMultiple($values, $ttl = null): bool
    {
        foreach ($values as $key => $value) $this->set($key, $value);
        return true;
    }

    /**
     * @param iterable $keys
     * @return bool
     */
    public function deleteMultiple($keys): bool
    {
        foreach ($keys as $key) $this->delete($key);
        return true;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @return bool
     * @throws Exception
     */
    public function setnx(string $key, mixed $value, int $ttl = self::CACHE_DEFALUT_TIMEOUT): bool
    {
        $key = self::generateKey($key);
        $table = $this->getOrCreateTable($key);
        if (!$ttl || $ttl <= 0) $ttl = self::CACHE_DEFALUT_TIMEOUT;
        if ($table->exist($key)) return false;
        return $this->set($key, $value, $ttl);
    }

    private function lockKey(string $key): string
    {
        return "lock:" . md5($key);
    }

    public function isLock(string $key): bool
    {
        $table = $this->getOrCreateTableByTableName('lock', 1024, 64);
        return $table->exist($this->lockKey($key));
    }

    public function lock(string $key, mixed $value = '1', int $ttl = self::LOCK_DEFAULT_TIMEOUT): bool
    {
        $table = $this->getOrCreateTableByTableName('lock', 1024, 64);
        $key = $this->lockKey($key);
        if ($table->exist($key)) return false;
        $table->set($key, ['cache_key' => $key, 'content' => strval($value), 'use_num' => 0]);
        if (!$ttl || $ttl <= 0) $ttl = self::LOCK_DEFAULT_TIMEOUT;
        $this->setTimer($ttl, $key);
        return true;
    }

    public function unlock(string $key): bool
    {
        $key = $this->lockKey($key);
        $table = $this->getSwooleTable('lock');
        isset($table) && $table->del($key);
        return true;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @return mixed
     * @throws Exception
     */
    public function getset(string $key, mixed $value, int $ttl = self::CACHE_DEFALUT_TIMEOUT): mixed
    {
        $key = self::generateKey($key);
        $table = $this->getOrCreateTable($key);
        if (!$ttl || $ttl <= 0) $ttl = self::CACHE_DEFALUT_TIMEOUT;
        $already_data = $table->get($key);
        $data = [
            'cache_key' => $key, 'content' => $this->serializeValue($value),
            'use_num' => $already_data['use_num'] ?? 0
        ];
        $table->set($key, $data);
        $this->setTimer($ttl, $key);
        return $this->unSerializeValue($already_data);
    }

    /**
     * 暂不提供此功能
     * @param string $srcKey
     * @param string $dstKey
     * @param bool $force
     * @return bool
     */
    public function rename(string $srcKey, string $dstKey, bool $force = false): bool
    {
        return false;
    }

    public function has($key): bool
    {
        $key = self::generateKey($key);
        $table = $this->getSwooleTable($this->getTableName($key));
        if (!$table) return false;
        return $table->exist($key);
    }

    public function incr(string $key, ?int $ttl = null, int $default_num = 1): int|bool
    {
        return $this->incrBy($key, $ttl, 1, $default_num);
    }

    public function decr(string $key, ?int $ttl = null, int $default_num = -1): int|bool
    {
        return $this->decrBy($key, $ttl, 1, $default_num);
    }

    public function incrBy(string $key, ?int $ttl = null, int $incr_amount = 1, int $default_num = 1): int|bool
    {
        $key = self::generateKey($key);
        $table = $this->getSwooleTable($this->getTableName($key));

        if ($default_num != 1 && !$table->exists($key)) {
            $ret = $table->set($key, ['cache_key' => $key, 'content' => $default_num, 'use_num' => 0]);
            $this->setTimer($ttl ?? self::CACHE_DEFALUT_TIMEOUT, $key);
            return $ret ? $default_num : false;
        } else {
            $ret = $table->incr($key, 'content', $incr_amount);
            $this->setTimer($ttl ?? self::CACHE_DEFALUT_TIMEOUT, $key);
            return $ret;
        }
    }

    public function decrBy(string $key, ?int $ttl = null, int $decr_amount = 1, int $default_num = -1): int|bool
    {
        $key = self::generateKey($key);
        $table = $this->getSwooleTable($this->getTableName($key));

        if ($default_num != -1 && !$table->exists($key)) {
            $ret = $table->set($key, ['cache_key' => $key, 'content' => $default_num, 'use_num' => 0]);
            $this->setTimer($ttl ?? self::CACHE_DEFALUT_TIMEOUT, $key);
            return $ret ? $default_num : false;
        } else {
            $ret = $table->decr($key, 'content', $decr_amount);
            $this->setTimer($ttl ?? self::CACHE_DEFALUT_TIMEOUT, $key);
            return $ret;
        }
    }

    private function setTimer($ttl, $key)
    {
        $this->timeHeap->setTimer($ttl, $key);
    }

}
