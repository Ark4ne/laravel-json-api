<?php

namespace Ark4ne\JsonApi\Resource\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Includes
{
    private static array $stack = [];

    private static array $cache = [];

    public static function through(string $type, callable $callable)
    {
        try {
            self::$stack[] = $type;

            return $callable();
        } finally {
            array_pop(self::$stack);
        }
    }

    public static function get(Request $request): array
    {
        return self::cache(implode('.', self::$stack), static fn($prefix) => Collection
            ::make(explode(',', $request->input('include', '')))
            ->when($prefix, fn($collect) => $collect
                ->filter(fn($included) => Str::startsWith($included, "$prefix."))
                ->map(fn($included) => Str::substr($included, Str::length("$prefix.")))
            )
            ->filter(fn($included) => $included)
            ->map(fn($include): string => Str::before(Str::after($include, $prefix), '.'))
            ->uniqueStrict()
            ->values()
            ->all());
    }

    private static function cache(string $prefix, callable $callable): array
    {
        return self::$cache[$prefix] ??= $callable($prefix);
    }
}
