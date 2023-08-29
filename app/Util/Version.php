<?php

namespace App\Util;

use Exception;

class Version
{
    /**
     * 比较两个版本大小
     * @param string $version1
     * @param string $version2
     * @return int
     * @throws Exception
     */
    public static function compare(string $version1, string $version2): int
    {
        if (self::check($version1) && self::check($version2)) {
            $version1_code = self::version_to_integer($version1);
            $version2_code = self::version_to_integer($version2);
            if (!$version1_code || !$version2_code) {
                throw new Exception('版本号格式错误');
            }
            if ($version1_code > $version2_code) {
                return 1;
            } elseif ($version1_code < $version2_code) {
                return -1;
            } else {
                return 0;
            }
        } else {
            throw new Exception('版本号格式错误');
        }
    }

    /**
     * 版本号是否合规
     * @param string $version
     * @return bool
     */
    public static function check(string $version): bool
    {
        $ret = preg_match('/^[0-9]{1,3}\.[0-9]{1,2}\.[0-9]{1,2}$/', $version);
        return (bool)$ret;
    }

    /**
     * 版本号转数字
     * @param string $version
     * @return int
     */
    public static function version_to_integer(string $version): int
    {
        if (self::check($version)) {
            list($major, $minor, $sub) = explode('.', $version);
            $integer_version = intval($major) * 10000 + intval($minor) * 100 + intval($sub);
            return intval($integer_version);
        } else {
            return 0;
        }
    }

    /**
     * 数字转版本号
     * @param int|string $version_code
     * @return string
     */
    public static function integer_to_version(int|string $version_code): string
    {
        if (is_numeric($version_code) && $version_code >= 10000) {
            $version = array();
            $version[0] = (int)($version_code / 10000);
            $version[1] = (int)($version_code % 10000 / 100);
            $version[2] = $version_code % 100;
            return implode('.', $version);
        } else {
            return '';
        }

    }
}