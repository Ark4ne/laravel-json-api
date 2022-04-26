<?php

namespace Ark4ne\JsonApi\Support\Traits;

trait HasLocalCache
{
    /**
     * @var array<class-string, array<string, mixed>>
     */
    private static array $cache = [];

    public static function flush(): void
    {
        self::$cache[static::class] = [];
    }

    /**
     * @template T
     *
     * @param string       $prefix
     * @param callable():T $callable
     *
     * @return T
     */
    private static function cache(string $prefix, callable $callable)
    {
        return self::$cache[static::class][$prefix] ??= $callable();
    }
}
