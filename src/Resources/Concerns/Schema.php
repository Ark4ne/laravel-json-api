<?php

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Resources\JsonApiCollection;
use Ark4ne\JsonApi\Support\FakeModel;
use Illuminate\Http\Request;
use ReflectionClass;

trait Schema
{
    private static array $schemas = [];

    public static function schema(Request $request = null): object
    {
        if (isset(self::$schemas[static::class])) {
            return self::$schemas[static::class];
        }

        $resource = self::new();

        if ($resource instanceof JsonApiCollection) {
            return $resource->collects::schema($request);
        }

        $request ??= new Request;

        $schema = (object)[
            'type' => null,
            'fields' => [],
            'relationships' => [],
        ];

        self::$schemas[static::class] = $schema;

        $schema->type = $resource->toType($request);
        $schema->fields = collect($resource->toAttributes($request))->keys()->all();

        foreach ($resource->toRelationships($request) as $name => $relation) {
            $schema->relationships[$name] = $relation->getResource()::schema();
        }

        return self::$schemas[static::class];
    }

    private static function new(): self
    {
        /** @var self $instance */
        $instance = (new ReflectionClass(static::class))->newInstanceWithoutConstructor();
        $instance->resource = new FakeModel;

        return $instance;
    }
}
