<?php

namespace App\Util;

use Closure;
use Hyperf\Collection\Arr;
use Hyperf\Collection\Collection;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\LengthAwarePaginatorInterface;
use Hyperf\Paginator\LengthAwarePaginator;
use RuntimeException;

class MyLengthAwarePaginator extends LengthAwarePaginator
{
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function setItems(Collection|array $items): static
    {
        $this->items = $items instanceof Collection ? $items : Collection::make($items);
        return $this;
    }

    public function each(?Closure $callback = null): static
    {
        if (empty($callback)) return $this;

        foreach ($this->items as $key => $item) {
            $this->items[$key] = $callback($item, $key);
        }

        return $this;
    }

    public function eachSkipNull(?Closure $callback = null): static
    {
        if (empty($callback)) return $this;

        $ary_is_assoc = CommonHelper::arrayIsAssoc($this->items);

        if ($ary_is_assoc) {
            $res = [];
            foreach ($this->items as $key => $item) {
                $each_res = $callback($item, $key);
                if (is_null($each_res)) continue;
                $res[] = $each_res;
            }
            $this->items = new Collection($res);
        } else {
            foreach ($this->items as $key => $item) {
                $each_res = $callback($item, $key);
                if (is_null($each_res)) {
                    unset($this->items[$key]);
                } else {
                    $this->items[$key] = $each_res;
                }
            }
        }

        return $this;
    }

    public static function make(Collection|array $items, int $total, int $perPage, int $currentPage, array $options = [])
    {
        $container = ApplicationContext::getContainer();
        if (!method_exists($container, 'make')) {
            throw new RuntimeException('The DI container does not support make() method.');
        }
        is_array($items) && $items = Collection::make($items);
        return $container->make(self::class, compact('items', 'total', 'perPage', 'currentPage', 'options'));
    }
}