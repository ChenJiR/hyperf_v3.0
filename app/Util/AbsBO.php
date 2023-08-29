<?php
declare(strict_types=1);

namespace App\Util;


use Hyperf\Contract\Arrayable;
use Hyperf\Codec\Json;
use Hyperf\Contract\Jsonable;
use Iterator;
use JsonSerializable;
use ReflectionClass;
use ReflectionMethod;


abstract class AbsBO implements Arrayable, Jsonable, JsonSerializable
{
    public function toArray(): array
    {
        $class = new ReflectionClass(static::class);
        $methods = array_map(
            function (ReflectionMethod $reflection_method) {
                return $reflection_method->getName();
            },
            $class->getMethods()
        );

        $attributes = [];
        foreach ($class->getProperties() as $val) {
            $getter_method_name = CommonHelper::snakeToCaml("get_{$val->getName()}");
            $attributes[$val->getName()] = $this->valToArray(
                in_array($getter_method_name, $methods) ? $this->$getter_method_name() : $this->{$val->getName()}
            );
        }

        return $attributes;
    }

    private function valToArray($val)
    {
        if ($val instanceof Arrayable) {
            return $val->toArray();
        } else if ($val instanceof Iterator || is_array($val)) {
            $val_ary = [];
            foreach ($val as $key => $item) {
                $val_ary[$key] = $this->valToArray($item);
            }
            return $val_ary;
        } else {
            return $val;
        }
    }

    public function __toString(): string
    {
        return Json::encode($this->jsonSerialize());
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param array $attributes
     * @return static
     */
    public static function instance(array $attributes = []): static
    {
        $instance = new static();
        $class = new ReflectionClass(static::class);

        $methods = array_map(
            function (ReflectionMethod $reflection_method) {
                return $reflection_method->getName();
            },
            $class->getMethods()
        );

        foreach ($class->getProperties() as $val) {
            if (isset($attributes[$val->getName()])) {
                $setter_method_name = CommonHelper::snakeToCaml("set_{$val->getName()}");
                in_array($setter_method_name, $methods)
                    ? $instance->$setter_method_name($attributes[$val->getName()])
                    : $instance->{$val->getName()} = $attributes[$val->getName()];
            }
        }
        return $instance;
    }
}
