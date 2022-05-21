<?php

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Resources\Skeleton;
use Ark4ne\JsonApi\Support\FakeModel;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use ReflectionClass;

trait Schema
{
    /**
     * @var array<class-string, Skeleton>
     */
    private static array $schemas = [];

    public static function schema(Request $request = null): Skeleton
    {
        if (isset(self::$schemas[static::class])) {
            return self::$schemas[static::class];
        }

        $resource = self::new();

        $request ??= new Request;

        self::$schemas[static::class] = $schema = new Skeleton(
            static::class,
            $resource->toType($request)
        );

        $schema->fields = (new Collection($resource->toAttributes($request)))->keys()->all();

        foreach ($resource->toRelationships($request) as $name => $relation) {
            $schema->relationships[$name] = $relation->getResource()::schema();
        }

        return self::$schemas[static::class];
    }

    /**
     * @throws \ReflectionException
     * @return static
     */
    private static function new(): static
    {
        /** @var static $instance */
        $instance = (new ReflectionClass(static::class))->newInstanceWithoutConstructor();
        $instance->resource = new FakeModel;

        return $instance;
    }
}
