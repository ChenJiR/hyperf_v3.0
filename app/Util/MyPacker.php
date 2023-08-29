<?php

namespace App\Util;

use Hyperf\Contract\PackerInterface;

class MyPacker implements PackerInterface
{
    public function pack($data): string
    {
        $type = gettype($data);
        $content = match ($type) {
            'array' => $data,
            'object' => serialize($data),
            'NULL' => null,
            default => strval($data),
        };
        return json_encode(['origindata_type' => $type, 'content' => $content]);
    }

    public function unpack(string $data)
    {
        if ($data === false) return null;
        if (is_null($data)) return null;
        if (is_numeric($data)) return $data;

        $json_value = json_decode($data, true);
        if (!$json_value) return $data;
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
}