<?php
declare(strict_types=1);

namespace App\Util;


use Exception;

class CrontabParse
{
    /**
     * 检测时间戳是否符合cron执行规则
     * @param string $cron
     * @param int|string|null $timestamp 时间戳
     * @return bool
     * @throws Exception
     */
    public static function checkByCronRule(string $cron, int|string|null $timestamp = null): bool
    {
        if (!$cron) {
            throw new Exception('规则配置错误');
        }
        $timestamp = $timestamp ?? time();
        $timestamp = is_numeric($timestamp) ? $timestamp : strtotime($timestamp);
        /**
         * 格式化时间戳并转成 分 时 日 月 周 格式
         * s  有前导零的秒数 00 到 59
         * i  有前导零的分钟数 00 到 59
         * G  小时，24 小时格式，没有前导零 0 到 23
         * j  月份中的第几天，没有前导零 1 到 31
         * n  数字表示的月份，没有前导零 1 到 12
         * w  星期中的第几天，数字表示 0（表示星期天）到 6（表示星期六）
         */
//        $time_cron = explode(' ', date('i G j n w', $timestamp));
        $raw_cron = self::parseCron($cron);
        $time_cron = match (count($raw_cron)) {
            default => explode(' ', date('i G j n w', $timestamp)),
            6 => explode(' ', date('s i G j n w', $timestamp))
        };
        foreach ($time_cron as $k => $piece) {
            if (!in_array($piece, $raw_cron[$k])) {
                return false;
            }
        }
        return true;
    }

    /**
     * 解析crontab表达式
     * @param string $cron 标准crontab表达式
     * @return array
     * 返回值为crontab可执行的范围
     * 例如
     *   * * * * * (每分钟执行) 会被解析为 [ [0,1,...,59] , [0,1,,,23] , [1,2,,,31] , [1,2,3,,,12] , [0,1,2,,,6] ]
     *   0 * * * * (每小时执行) 会被解析为 [ [0] , [0,1,,,23] , [1,2,,,31] , [1,2,3,,,12] , [0,1,2,,,6] ]
     *   0 6 * * * (每天6点执行) 会被解析为 [ [0] , [6] , [1,2,,,31] , [1,2,3,,,12] , [0,1,2,,,6] ]
     *   0 6,12 * * * (每天6点，12点执行) 会被解析为 [ [0] , [6,12] , [1,2,,,31] , [1,2,3,,,12] , [0,1,2,,,6] ]
     *   10 6-12 * * * (每天6点到12点每小时第10分钟执行) 会被解析为 [ [10] , [6,7,,,12] , [1,2,,,31] , [1,2,3,,,12] , [0,1,2,,,6] ]
     *   *\/10 * * * * (每10分钟执行) 会被解析为 [ [0,10,,,50] , [0,1,,,23] , [1,2,,,31] , [1,2,3,,,12] , [0,1,2,,,6] ]
     *   0 7-9 * * 3 (每周三的7-9点每小时执行) 会被解析为 [ [0] , [7,8,9] , [1,2,,,31] , [1,2,3,,,12] , [3] ]
     *
     * 也就是说 只要判断当前时间的 分钟数、小时数、天数、月份数、星期中的第几天 都在可执行范围内 则判断为可执行
     * @throws Exception
     */
    public static function parseCron(string $cron): array
    {
        // 解析后的数组
        $raw = [];
        $dimensions = [
            [0, 59],//Minutes
            [0, 23],//Hours
            [1, 31],//Days
            [1, 12],//Months
            [0, 6], //Weekdays
        ];
        // 将命令用空格分割成数组
        $cronArr = explode(' ', $cron); // ['*/5', '*', '*', '*', '*']
        if (count($cronArr) < 5) {
            throw new Exception('crontabRule配置错误' . $cron);
        } else if (count($cronArr) == 6) {
            //秒级规则
            array_unshift($dimensions, [0, 59]);
        } else if (count($cronArr) > 6) {
            $cronArr = array_slice($cronArr, 0, 5);
        }
        // 针对每一个位置进行解析
        foreach ($cronArr as $key => $item) {
            $raw[$key] = [];
            // 标记是哪种命令格式，通过使用的crontab命令可以分为两大类
            // 1.每几分钟或每小时这样的 */10 * * * *
            // 2.几点几分这样的 10,20,30-50 * * * *
            list($repeat, $every) = explode('/', $item, 2) + [false, 1];
            if ($repeat === '*') {
                $raw[$key] = range($dimensions[$key][0], $dimensions[$key][1]);
            } else {
                // 处理逗号拼接的命令
                $tmpRaw = explode(',', $item);
                foreach ($tmpRaw as $tmp) {
                    // 处理10-20这样范围的命令
                    $tmp = explode('-', $tmp, 2);
                    isset($tmp[1])
                        ? $raw[$key] = array_merge($raw[$key], range($tmp[0], $tmp[1]))
                        : $raw[$key][] = $tmp[0];
                }
            }
            // 判断*/10 这种类型的
            if ($every > 1) {
                foreach ($raw[$key] as $k => $v) {
                    if ($v % $every != 0) {
                        unset($raw[$key][$k]);
                    }
                }
            }
        }
        return $raw;
    }
}