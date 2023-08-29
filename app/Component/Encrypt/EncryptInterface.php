<?php

namespace App\Component\Encrypt;

interface EncryptInterface
{
    public function __construct(string $key);

    public function encode(string $string): string;

    public function decode(string $string): string;

}