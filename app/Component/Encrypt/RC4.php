<?php

namespace App\Component\Encrypt;

class RC4 implements EncryptInterface
{

    const ENCRYPT_KEY = 'xxx';

    protected string $key;
    protected array $box;

    public function __construct(string $key = self::ENCRYPT_KEY)
    {
        $this->key = $key;
        $this->box = range(0, 255);
    }

    public function encode(string $string): string
    {
        return $this->coreEncode($string, $this->key);
    }

    public function decode(string $string): string
    {
        return $this->coreEncode($string, $this->key);
    }

    // 核心加密方法
    private function coreEncode(string $string, string $key): string
    {
        $s = $this->box;
        $j = 0;
        for ($i = 0; $i < 256; $i++) {
            $j = ($j + $s[$i] + ord($key[$i % strlen($key)])) % 256;
            // 交换s[i]和s[j]
            $temp = $s[$i];
            $s[$i] = $s[$j];
            $s[$j] = $temp;
        }

        $i = $j = 0;
        $cipher = '';
        for ($k = 0; $k < strlen($string); $k++) {
            $i = ($i + 1) % 256;
            $j = ($j + $s[$i]) % 256;
            // 交换s[i]和s[j]
            $temp = $s[$i];
            $s[$i] = $s[$j];
            $s[$j] = $temp;

            $cipher .= $string[$k] ^ chr($s[($s[$i] + $s[$j]) % 256]);
        }

        return $cipher;
    }
}