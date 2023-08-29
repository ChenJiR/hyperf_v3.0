<?php
declare(strict_types=1);
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/1 0001
 * Time: 11:02
 */

namespace App\Util;

use function time, strtotime, date, gmstrftime, floor;

class TimeHelper
{
    const HOUR_S = 60 * 60;  //每小时秒数

    const DAY_S = 60 * 60 * 24; //每天秒数

    const WEEK_S = 60 * 60 * 24 * 7; //每周秒数


    public static function getStrTime(?int $time = null): string
    {
        return isset($time) ? date('Y-m-d H:i:s', $time) : static::nowTime();
    }

    public static function formatTime(null|int|string $time = null): string
    {
        return isset($time)
            ? (is_numeric($time) ? static::getStrTime(intval($time)) : self::getStrTime(strtotime($time)))
            : static::nowTime();
    }

    public static function nowTime(): string
    {
        return date('Y-m-d H:i:s');
    }

    public static function nowDate(): string
    {
        return date('Y-m-d');
    }

    public static function yesterdayDate(): string
    {
        return date("Y-m-d", strtotime("-1 day"));
    }

    public static function tomorrowDate(): string
    {
        return date("Y-m-d", strtotime("+1 day"));
    }

    //标准时间格式转换为日期
    public static function strtoDate($time): string
    {
        return date("Y-m-d", strtotime($time));
    }

    //时间戳转换为时间文案
    public static function timeToText($time): string
    {
        $text = match (date("Y-m-d", $time)) {
            self::yesterdayDate() => '昨日',
            self::tomorrowDate() => '明日',
            self::nowDate() => '今日',
            default => date("m月d日", $time),
        };
        $H_text = date("H", $time) . '点';
        $i = date("i", $time);
        $i_text = ($i == '00') ? '' : ($i == '30' ? '半' : $i . '分');
        return $text . $H_text . $i_text;
    }


    /**
     * 获取下一个周数的日期或时间戳 , 注意 周日的周数为0
     * @param $weeknum int 周几
     * @param bool $datetime 返回为时间戳还是日期 默认为日期
     * @return bool|false|float|int|string
     */
    public static function nextWeekNum(int $weeknum, bool $datetime = true): float|bool|int|string
    {
        if (!in_array($weeknum, [0, 1, 2, 3, 4, 5, 6])) {
            return false;
        }
        $now = strtotime(date('Y-m-d'));
        $nowDay = date('w', $now);
        if ($nowDay == $weeknum) {
            $returnTime = $now + self::WEEK_S;
        } else if ($nowDay < $weeknum) {
            $day_num = $weeknum - $nowDay;
            $returnTime = $now + ($day_num * self::DAY_S);
        } else {
            $day_num = $nowDay - $weeknum;
            $returnTime = $now + (7 - $day_num) * self::DAY_S;
        }
        return $datetime ? date('Y-m-d H:i:s', $returnTime) : $returnTime;
    }

    /**
     * 把秒数转换为时分秒的格式
     * @param Int $times 时间，单位 秒
     * @return String
     */
    public static function secToTime(int $times): string
    {
        if ($times < 3600 * 24) {
            return gmstrftime('%H:%M:%S', $times);
        }
        $result = '00:00:00';
        if ($times > 0) {
            $hour = floor($times / 3600);
            $minute = floor(($times - 3600 * $hour) / 60);
            $second = floor((($times - 3600 * $hour) - 60 * $minute) % 60);
            $result = $hour . ':' . $minute . ':' . $second;
        }
        return $result;
    }

    public static function displayTime(int|string|null $datetime)
    {
        if (empty($datetime)) return '';

        $createTimestamp = is_numeric($datetime) ? intval($datetime) : strtotime($datetime);
        $nowTimestamp = time();
        $diff = $nowTimestamp - $createTimestamp;

        if ($diff > 3 * 30 * 86400) {
            // 大于3个月
            if (date('Y') == date('Y', $createTimestamp)) {
                return date('m月d日 H:i', $createTimestamp);
            } else {
                return date('Y年m月d日', $createTimestamp);
            }
        } elseif ($diff > 30 * 86400) {
            // 大于1个月
            $months = floor($diff / 2592000);
            return $months . '月前';
        } elseif ($diff > 7 * 86400) {
            // 大于7 天
            $weeks = floor($diff / 604800);
            return $weeks . '周前';
        } elseif ($diff > 86400) {
            // 大于1天
            $days = floor($diff / 86400);
            return $days . '天前';
        } elseif ($diff > 3600) {
            // 大于1小时
            $hours = floor($diff / 3600);
            return $hours . '小时前';
        } elseif ($diff > 300) {
            $minutes = floor($diff / 60);
            return $minutes . '分钟前';
        } else {
            return '刚刚';
        }
    }

    /**
     * 获取简短时间
     *
     * @Author: whk
     * @DateTime: 2023/5/8 17:55
     * @return string
     */
    public static function getShortTime($targetTime)
    {
        // 今天最大时间
        $todayLast = strtotime(date('Y-m-d 23:59:59'));
        $year = date('Y', time());
        $agoTime = $todayLast - $targetTime;
        $agoDay = floor($agoTime / 86400);
        $weekArray = array("日", "一", "二", "三", "四", "五", "六"); //先定义一个数组
        if ($agoDay == 0) {
            $result = date('H:i', $targetTime); // 今天
        } elseif ($agoDay == 1) {
            $result = '昨天' . date('H:i', $targetTime); // 昨天
        } elseif ($agoDay > 2 && $agoDay <= 7) {
            $result = '星期' . $weekArray[date("w", $targetTime)] . ' ' . date('H:i', $targetTime); // 近7天
        } elseif (date('Y', $targetTime) == $year) {
            $result = intval(date('m', $targetTime)) . '月' . intval(date('d', $targetTime)) . '日' . ' ' . date('H:i', $targetTime);
        } else {
            $result = date('Y-m-d H:i:s', $targetTime);
        }
        return $result;
    }
}
