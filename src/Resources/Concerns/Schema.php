<?php

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Descriptors\Describer;
use Ark4ne\JsonApi\Descriptors\Relations\Relation;
use Ark4ne\JsonApi\Descriptors\Resolver;
use Ark4ne\JsonApi\Resources\Skeleton;
use Ark4ne\JsonApi\Support\FakeModel;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use ReflectionClass;

trait Schema
{
    use Resolver;

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

        $schema->fields = (new Collection($resource->mergeValues($resource->toAttributes($request))))
            ->map(fn($value, $key) => Describer::retrieveName($value, $key))
            ->values()
            ->all();

        foreach ($resource->toRelationships($request) as $name => $relation) {
            if ($relation instanceof Relation) {
                $relationship = $relation->related();
                $name = Describer::retrieveName($relation, $name);
            } else {
                $relationship = $relation->getResource();
            }
            $schema->relationships[$name] = $relationship::schema();
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
