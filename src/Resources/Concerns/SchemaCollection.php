<?php

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Resources\Skeleton;
use Illuminate\Http\Request;
use ReflectionClass;

trait SchemaCollection
{
    public static function schema(Request $request = null): Skeleton
    {
        return self::new()->collects::schema($request);
    }

    /**
     * @throws \ReflectionException
     * @return static
     */
    private static function new(): static
    {
        return (new ReflectionClass(static::class))->newInstanceWithoutConstructor();
    }
}
