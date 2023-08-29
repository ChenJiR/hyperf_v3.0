<?php
declare(strict_types=1);

namespace App\Util;


use App\Listener\RunEnvListener;
use Hyperf\Codec\Json;
use Hyperf\Collection\Collection;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Context\ApplicationContext;
use function Hyperf\Support\env;
use function trim, uniqid, rand, substr, microtime, mt_rand, md5, strlen;
use function is_int, is_string;
use function strtoupper, strtolower;

class CommonHelper
{
    /**
     * 检测值是否为空
     * @param $value
     * @return bool
     */
    public static function isEmpty($value): bool
    {
        return $value === '' || $value === [] || $value === null || is_string($value) && trim($value) === '';
    }

    /**
     * 检测数组中的值是否存在或为空
     * @param array $ary
     * @param $key
     * @return bool
     */
    public static function arrayValueIsEmpty(array $ary, $key): bool
    {
        return !isset($ary[$key]) || self::isEmpty($ary[$key]);
    }

    /**
     * 生成随机字符
     * @param string $prefix
     * @return string
     */
    public static function uniqCode(string $prefix = ''): string
    {
        return uniqid($prefix) . rand(10, 99) . uniqid() . substr(microtime(), 4, 3);
    }

    /**
     * 生成随机字符串
     * @param int $length 生成随机字符串的长度
     * @param string $char 组成随机字符串的字符串
     * @return bool|string $string 生成的随机字符串
     * @author yanglb@immatchu.com
     */
    public static function strRand(int $length = 32, string $char = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'): bool|string
    {
        if (!is_int($length) || $length < 0) {
            return false;
        }
        $string = '';
        for ($i = $length; $i > 0; $i--) {
            $string .= $char[mt_rand(0, strlen($char) - 1)];
        }
        return $string;
    }

    /**
     * 下划线转驼峰
     */
    public static function snakeToCaml(string $str, string $snake_character = '_-'): string
    {
        return preg_replace_callback("/([$snake_character]+([a-z]{1}))/i", function ($matches) {
            return strtoupper($matches[2]);
        }, $str);
    }

    /**
     * 驼峰转下划线
     */
    public static function camlToSnake(string $str, string $snake_character = '_'): string
    {
        return preg_replace_callback('/([A-Z]{1})/', function ($matches) use ($snake_character) {
            return $snake_character . strtolower($matches[0]);
        }, $str);
    }

    /**
     * @param string $str
     * @param string $code_start_opt
     * @param string $code_end_opt
     * @return string
     */
    public static function strCodeReplace(string $str, string $code_start_opt = '<?php', string $code_end_opt = '?>'): string
    {
        $str_search = [];
        $str_replace = [];
        for ($start_pos = 0; $start_pos < strlen($str) - strlen($code_start_opt); $start_pos++) {
            if (substr($str, $start_pos, strlen($code_start_opt)) == $code_start_opt) {
                $end_pos = stripos($str, $code_end_opt, $start_pos);
                $search_str = substr($str, $start_pos, $end_pos - $start_pos + strlen($code_end_opt));
                $code = str_replace([$code_start_opt, $code_end_opt], '', $search_str);
                $return_value = @eval($code);
                if (!$return_value) continue;
                $str_search[] = $search_str;
                $str_replace[] = $return_value;
            }
        }
        return str_replace($str_search, $str_replace, $str);
    }

    /**
     * 获取客户端IP
     */
    public static function getClientIp(): string
    {
        $request = ApplicationContext::getContainer()->get(RequestInterface::class);
        $headers = $request->getHeaders();

        if (isset($headers['x-forwarded-for'][0]) && !empty($headers['x-forwarded-for'][0])) {
            return $headers['x-forwarded-for'][0];
        } elseif (isset($headers['x-real-ip'][0]) && !empty($headers['x-real-ip'][0])) {
            return $headers['x-real-ip'][0];
        } else {
            $serverParams = $request->getServerParams();
            if (isset($serverParams['http_client_ip'])) {
                return $serverParams['http_client_ip'];
            } elseif (isset($serverParams['http_x_real_ip'])) {
                return $serverParams['http_x_real_ip'];
            } elseif (isset($serverParams['http_x_forwarded_for'])) {
                //部分CDN会获取多层代理IP，所以转成数组取第一个值
                $arr = explode(',', $serverParams['http_x_forwarded_for']);
                return $arr[0];
            } else {
                return $serverParams['remote_addr'] ?? '';
            }
        }
    }


    /**
     * 函数说明：富文本数据进行转换成文本
     *
     * @access  public
     * @param   $content  string  富文本数据
     * @return  string    不包含标签的文本
     */
    public static function contentFormat($content)
    {
        $data = $content;
        $formatData_01 = htmlspecialchars_decode($data); //把一些预定义的 HTML 实体转换为字符
        $formatData_02 = strip_tags($formatData_01);     //函数剥去字符串中的 HTML、XML 以及 PHP 的标签,获取纯文本内容
        return $formatData_02;
    }

    /**
     * 获取时间格式化数据
     * @param $targetTime
     * @return false|string
     */
    public static function getNoticeTime($targetTime)
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

    public static function videoTranscodingUrl($resource_url)
    {
        $prefixUrl = '1302344781.vod2.myqcloud.com';
        $suffix = 'adp.10.m3u8';
        $new_suffix = 'v.f100040.mp4';
        $obs_repaly = env('OBS_URL', 'obs.dingxinwen.com');//华为云
        // 判断资源的类型是否正确
        if ($resource_url != '' && $resource_url != '[]') {
            // 判断为空 - 前端传输过来的是json串 但是他其实是一个数组 json话的东西
            if (is_string($resource_url)) {
                $getResourceUrl = json_decode($resource_url, true);
            } else if (is_array($resource_url)) {
                $getResourceUrl = $resource_url;
            }
            if (empty($resource_url) || !is_array($getResourceUrl) || empty($getResourceUrl[0])) {
                return $resource_url;
            }
        }

        // 当前是一个json串 转化之后是一个数组
        foreach ($getResourceUrl as $k => &$v) {
            if (strpos($v, $prefixUrl) !== false) {
                if (strpos($v, $suffix) !== false) {
                    $v = str_replace($suffix, $new_suffix, $v);
                }
            }
            if ((strpos($v, $obs_repaly) !== false) && self::isTypeResourceUrl($v, 'video') && stripos($v, '_1440.mp4') === false) {
                $v = self::getResourceUseAddress($v);
                // 判断一下当前资源的url是否是视频类型 如果是视频类型我们需要在这个url后面拼接一下特殊字符
                $explodeResource = explode('.', $v);
                if (substr($explodeResource[count($explodeResource) - 2], -5) != '_1440') {
                    $explodeResource[count($explodeResource) - 2] .= '_1440';
                    unset($explodeResource[count($explodeResource) - 1]);
                    $v = implode('.', $explodeResource) . '.mp4';
                }
            }
            $v = str_ireplace('_1440.mov', '_1440.mp4', $v);
        }
        return $getResourceUrl;
    }

    public static function isTypeResourceUrl($str, $type)
    {
        $fileType = [
            'file' => ['doc', 'docx', 'xlsx', 'txt', 'apk'],
            'image' => ['png', 'gif', 'jpg', 'jpeg'],
            'video' => ['mp4', 'mov', 'MP4', 'm3u8'],
            'audio' => ['mp3', 'wav', 'pcm'],
        ];
        $videoNameStrArr = explode('.', $str);
        $videoNameStrArrCount = count($videoNameStrArr);
        $suffix = $videoNameStrArr[$videoNameStrArrCount - 1];
        if (in_array($suffix, $fileType[$type])) {
            return true;
        }
        return false;
    }

    public static function getResourceUseAddress($data)
    {
        if (empty($data)) {
            return '[]';
        }
        if (is_array($data)) {
            $data = stripslashes(json_encode($data, JSON_UNESCAPED_UNICODE));
        } else {
            if (strpos($data, 'http') !== 0) {
                $data = 'https://' . $data;
            }
        }
        return $data;
    }

    public static function JsonDecode(?string $json, mixed $default = [])
    {
        if (empty($json)) return $default;

        try {
            return Json::decode($json);
        } catch (\Exception) {
            return $default;
        }
    }

    public static function arrayIsAssoc(array|Collection $ary): bool
    {
        $ary = $ary instanceof Collection ? $ary->all() : $ary;
        foreach (array_keys($ary) as $key => $ary_key) {
            if ($key !== $ary_key) return false;
        }
        return true;
    }

    public static function parseNumber($number = 0)
    {
        if ($number >= 10000) {
            # 判断是否超过w
            $newNum = bcdiv(strval($number + 1000), strval(10000), 1) . 'w';
        } elseif ($number >= 1000) {
            # 判断是否超过k
            $newNum = bcdiv(strval($number + 100), strval(1000), 1) . 'k';
        } else {
            $newNum = $number;
        }
        return $newNum;
    }

    public static function arrayFilterKey(array $array, array $enable_key): array
    {
        return array_filter(
            $array,
            function ($k) use ($enable_key) {
                return in_array($k, $enable_key);
            },
            ARRAY_FILTER_USE_KEY
        );
    }

}