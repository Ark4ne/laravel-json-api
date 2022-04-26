<?php

namespace Ark4ne\JsonApi\Support;

use Ark4ne\JsonApi\Support\Traits\HasLocalCache;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class Includes
{
    use HasLocalCache;

    /**
     * @var string[]
     */
    private static array $stack = [];

    /**
     * Defined current resource-type/relation through callback
     *
     * @param string   $type
     * @param callable $callable
     *
     * @return mixed
     */
    public static function through(string $type, callable $callable): mixed
    {
        try {
            self::$stack[] = $type;

            return $callable();
        } finally {
            array_pop(self::$stack);
        }
    }

    /**
     * Return remaining includes for current resource
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return string[]
     */
    public static function get(Request $request): array
    {
        return array_keys(self::currentStack($request));
    }

    /**
     * Return if a resource-type/relation is included
     *
     * @param \Illuminate\Http\Request $request
     * @param string                   $type
     *
     * @return bool
     */
    public static function include(Request $request, string $type): bool
    {
        return in_array($type, self::get($request), true);
    }

    /**
     * Return remaining includes
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return string[]
     */
    public static function includes(Request $request): array
    {
        return array_keys(Arr::dot(self::currentStack($request)));
    }

    /**
     * @param string $include
     *
     * @return array<string, mixed>
     */
    public static function parse(string $include): array
    {
        return self::cache("parse:$include", static fn() => Arr::undot(
            array_fill_keys(explode(',', $include), []))
        );
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array<string, mixed>
     */
    private static function currentStack(Request $request): array
    {
        return Arr::get(
            self::parse($request->input('include', '')),
            implode('.', self::$stack) ?: null,
            []
        );
    }
}
