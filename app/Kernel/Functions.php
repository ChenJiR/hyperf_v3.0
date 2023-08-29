<?php
declare(strict_types=1);

use Hyperf\Context\ApplicationContext;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Hyperf\ExceptionHandler\Formatter\FormatterInterface;


if (!function_exists('di')) {
    /**
     * Finds an entry of the container by its identifier and returns it.
     * @param string|null $id
     * @return mixed
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    function di(?string $id = null): mixed
    {
        $container = ApplicationContext::getContainer();
        if ($id) {
            return $container->get($id);
        }

        return $container;
    }
}

if (! function_exists('format_throwable')) {
    /**
     * Format a throwable to string.
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    function format_throwable(Throwable $throwable): string
    {
        return di()->get(FormatterInterface::class)->format($throwable);
    }
}