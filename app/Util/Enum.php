<?php
declare(strict_types=1);

namespace App\Util;


use ReflectionClass;

abstract class Enum
{
    const __default = null;

    /**
     * @var mixed
     */
    protected static mixed $value;

    protected function __construct($value = null)
    {
        return static::$value = is_null($value) ? static::__default : $value;
    }

    /**
     * @param $name
     * @param $arguments
     * @return static
     */
    public static function __callStatic($name, $arguments)
    {
        $refClass = static::getRefClass();
        $constant = $refClass->getConstant(strtoupper($name));
        $construct = $refClass->getConstructor();
        $construct->setAccessible(true);
        return new static($constant);
    }

    public function __toString()
    {
        return (string)static::$value;
    }

    protected static function getRefClass(): ReflectionClass
    {
        return new ReflectionClass(static::class);
    }

    /**
     * @param $val
     * @return bool
     */
    public static function isValid($val): bool
    {
        return in_array($val, static::toArray());
    }

    public static function toArray(): array
    {
        return static::getEnumMember();
    }

    public static function getEnumMember(): array
    {
        return array_filter(static::getRefClass()->getConstants());
    }

    public static function getValues(): array
    {
        return array_values(static::getEnumMember());
    }

    public static function keys(): array
    {
        return array_keys(static::getEnumMember());
    }

    public static function getKey($key, $default)
    {
        return static::$key() ?? $default;
    }

    public function format($type = null): bool|int|string
    {
        return match ($type) {
            ctype_digit(static::$value) || is_int($type) => (int)static::$value,
            $type === true => (bool)filter_var(static::$value, FILTER_VALIDATE_BOOLEAN),
            default => static::$value,
        };
    }
}

