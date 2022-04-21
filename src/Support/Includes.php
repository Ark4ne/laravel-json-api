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
        return collect(self::depth($request))
            ->map(fn($include): string => explode('.', $include, 2)[0])
            ->uniqueStrict()
            ->values()
            ->all();
    }

    public static function depth(Request $request): array
    {
        return self::cache(implode('.', self::$stack), static fn($prefix) => Collection
            ::make(explode(',', $request->input('include', '')))
            ->when($prefix, fn($collect) => $collect
                ->filter(fn($included) => Str::startsWith($included, "$prefix."))
                ->map(fn($included) => Str::substr($included, Str::length("$prefix.")))
            )
            ->filter(fn($included) => $included)
            ->uniqueStrict()
            ->values()
            ->all());
    }

    public static function include(Request $request, string $type): bool
    {
        return in_array($type, self::get($request), true);
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    private static function cache(string $prefix, callable $callable): array
    {
        return self::$cache[$prefix] ??= $callable($prefix);
    }
}
