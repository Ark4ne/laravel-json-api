<?php

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Descriptors\Describer;
use Ark4ne\JsonApi\Descriptors\Relations\Relation;
use Ark4ne\JsonApi\Descriptors\Resolver;
use Ark4ne\JsonApi\Descriptors\Values\ValueStruct;
use Ark4ne\JsonApi\Resources\Skeleton;
use Ark4ne\JsonApi\Support\FakeModel;
use Ark4ne\JsonApi\Support\Values;
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

    public static function schema(null|Request $request = null): Skeleton
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

        $schema->fields = (new Collection(Values::mergeValues($resource->toAttributes($request))))
            ->flatMap(function ($value, $key) use ($resource) {
                $collection = (new Collection([Describer::retrieveName($value, $key) => true]));

                if ($value instanceof ValueStruct) {
                    return $collection->merge(self::structFields($resource, $key, $value, $key));
                }

                return $collection;
            })
            ->keys()->all();

        foreach (Values::mergeValues($resource->toRelationships($request)) as $name => $relation) {
            if ($relation instanceof Relation) {
                $relationship = $relation->related();
                $name = Describer::retrieveName($relation, $name);
            } else {
                $relationship = $relation->getResource();
            }
            $schema->relationships[$name] = $relationship::schema();

            $load = $relation->load();

            if ($load === false) {
                $schema->loads[$name] = false;
            }
            elseif ($load === true) {
                $schema->loads[$name] = $name;
            }
            elseif (is_string($load)) {
                $schema->loads[$name] = $load;
            }
            else {
                foreach ((array)$load as $key => $value) {
                    $schema->loads[$name][$key] = $value;
                }
            }
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

    private static function structFields(mixed $resource, string $key, ValueStruct $struct, null|string $prefix = null): Collection
    {
        return (new Collection(Values::mergeValues(($struct->retriever())($resource, $key))))
            ->flatMap(function($value, $key) use ($resource, $prefix) {
                $prefixed = Describer::retrieveName($value, $key, $prefix);

                if ($value instanceof ValueStruct) {
                    return self::structFields($resource, $key, $value, $prefixed);
                }

                return [$prefixed => $value];
            });
    }
}
