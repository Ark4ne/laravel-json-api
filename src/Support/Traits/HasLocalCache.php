<?php

namespace Ark4ne\JsonApi\Support\Traits;

trait HasLocalCache
{
    private static array $cache = [];

    public static function flush(): void
    {
        self::$cache[static::class] = [];
    }

    private static function cache(string $prefix, callable $callable): array
    {
        return self::$cache[static::class][$prefix] ??= $callable();
    }
}
