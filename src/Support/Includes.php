<?php

namespace Ark4ne\JsonApi\Support;

use Ark4ne\JsonApi\Support\Traits\HasLocalCache;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class Includes
{
    use HasLocalCache;

    private static array $stack = [];

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
        $prefix = implode('.', self::$stack);
        return self::cache("get:$prefix", static fn() => array_keys(
            Arr::get(self::parse($request->input('include', '')), $prefix ?: null, [])
        ));
    }

    public static function parse(string $include): array
    {
        return self::cache("parse:$include", static fn() => Arr::undot(
            array_fill_keys(explode(',', $include), []))
        );
    }

    public static function include(Request $request, string $type): bool
    {
        return in_array($type, self::get($request), true);
    }
}
