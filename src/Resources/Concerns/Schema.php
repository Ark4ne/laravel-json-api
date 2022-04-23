<?php

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Resources\JsonApiCollection;
use Ark4ne\JsonApi\Support\FakeModel;
use Illuminate\Http\Request;

trait Schema
{
    private static array $schemas = [];

    public static function schema(Request $request = null): object
    {
        $request ??= new Request;

        $resource = new static(new FakeModel);

        if ($resource instanceof JsonApiCollection) {
            return $resource->collects::schema($request);
        }

        if (isset(self::$schemas[static::class])) {
            return self::$schemas[static::class];
        }

        $schema = (object)[
            'type' => null,
            'fields' => [],
            'relationships' => [],
        ];

        self::$schemas[static::class] = $schema;

        $schema->type = $resource->toType($request);
        $schema->fields = array_keys($resource->toAttributes($request));

        foreach ($resource->toRelationships($request) as $name => $relation) {
            $schema->relationships[$name] = $relation->getResource()::schema();
        }

        return self::$schemas[static::class];
    }
}
