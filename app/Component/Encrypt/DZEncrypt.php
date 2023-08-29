<?php

namespace App\Component\Encrypt;


use function chr;
use function md5;
use function ord;
use function sprintf;
use function strlen;
use function substr;
use function time;
use function microtime;

class DZEncrypt implements EncryptInterface
{

    const ENCRYPT_KEY = 'xxx';

    private int $randomkey_length;

    //秘钥A（参与加解密）
    private string $keya;
    //秘钥B（参与数据完整性验证）
    private string $keyb;
    private array $box = [];

    public function __construct(string $key = self::ENCRYPT_KEY, int $microtimekey_length = 4)
    {
        $this->randomkey_length = $microtimekey_length;
        $encrypt_key = $key;

        $md5key = md5($encrypt_key);
        $this->keya = md5(substr($md5key, 0, 16));
        $this->keyb = md5(substr($md5key, 16, 16));
        $this->box = range(0, 255);
    }

    public function encode(string $string, int $expiry = 0): string
    {
        $randomkey = $this->randomkey_length ? substr(md5(microtime()), -$this->randomkey_length) : '';
        $cryptkey = $this->keya . md5($this->keya . $randomkey);
        $key_length = strlen($cryptkey);
        $rndkey = [];
        for ($i = 0; $i <= 255; $i++) {
            $rndkey[$i] = ord($cryptkey[$i % $key_length]);
        }

        return $randomkey . $this->coreEncode($string, $rndkey, $expiry ? $expiry + time() : 0);
    }

    public function batchEncode(array $string_ary, int $expiry = 0): array
    {
        if (empty($string_ary)) return [];

        $randomkey = $this->randomkey_length ? substr(md5(microtime()), -$this->randomkey_length) : '';
        $cryptkey = $this->keya . md5($this->keya . $randomkey);
        $key_length = strlen($cryptkey);
        $rndkey = [];
        for ($i = 0; $i <= 255; $i++) {
            $rndkey[$i] = ord($cryptkey[$i % $key_length]);
        }

        $result = [];
        $ttl = $expiry ? $expiry + time() : 0;
        foreach ($string_ary as $string) {
            $result[$string] = $randomkey . $this->coreEncode($string, $rndkey, $ttl);
        }
        return $result;
    }

    // 核心加密方法
    private function coreEncode(string $string, array $rndkey, $ttl): string
    {
        $box = $this->box;
        $string =
            sprintf('%010d', $ttl) .
            substr(md5($string . $this->keyb), 0, 16) .
            $string;
        $string_length = strlen($string);
        $result = '';
        for ($j = $i = 0; $i < 256; $i++) {
            $j = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }
        for ($a = $j = $i = 0; $i < $string_length; $i++) {
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }
        $result = str_replace('+', '-', base64_encode($result));
        return str_replace('=', '', $result);
    }

    /**
     * @throws EncryptException
     */
    public function decode(string $string): string
    {
        $randomkey = $this->randomkey_length ? substr($string, 0, $this->randomkey_length) : '';
        $cryptkey = $this->keya . md5($this->keya . $randomkey);
        $key_length = strlen($cryptkey);
        $string = str_replace('-', '+', substr($string, $this->randomkey_length));
        $string = base64_decode($string);
        $string_length = strlen($string);
        $rndkey = [];
        for ($i = 0; $i <= 255; $i++) {
            $rndkey[$i] = ord($cryptkey[$i % $key_length]);
        }
        $result = '';
        $box = $this->box;
        for ($j = $i = 0; $i < 256; $i++) {
            $j = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }
        for ($a = $j = $i = 0; $i < $string_length; $i++) {
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }

        $ttl = intval(substr($result, 0, 10));
        if ($ttl && $ttl - time() < 0) {
            throw new EncryptException('解密字符串已过期');
        }
        if (substr($result, 10, 16) == substr(md5(substr($result, 26) . $this->keyb), 0, 16)) {
            return substr($result, 26);
        } else {
            throw new EncryptException('解密字符完整性校验错误');
        }
    }
}