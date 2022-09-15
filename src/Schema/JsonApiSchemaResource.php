<?php

namespace Ark4ne\JsonApi\Schema;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\JsonApi\Descriptors\Valuable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * @extends JsonApiResource<\Ark4ne\JsonApi\Schema\JsonApiSchema>
 */
class JsonApiSchemaResource extends JsonApiResource
{
    public function toType(Request $request): string
    {
        return $this->resource->type();
    }

    protected function toIdentifier(Request $request): int|string
    {
        return $this->resource->resource->{$this->resource->identifier()};
    }

    public function toAttributes(Request $request): iterable
    {
        return (new Collection($this->resource->attributes()))
            ->map(fn(Valuable $value, string $field) => $value->valueFor(
                $request,
                $this->resource->resource,
                $field
            ));
    }

    public function toRelationships(Request $request): iterable
    {
        return (new Collection($this->resource->relationships()))
            ->map(fn(Valuable $value, string $field) => $value->valueFor(
                $request,
                $this->resource->resource,
                $field
            ));
    }

    public function toResourceMeta(Request $request): ?iterable
    {
        return (new Collection($this->resource->meta()))
            ->map(fn(Valuable $value, string $field) => $value->valueFor(
                $request,
                $this->resource->resource,
                $field
            ));
    }

    public function toLinks(Request $request): ?iterable
    {
        return array_filter([
            'self' => $this->resource->route()
        ]);
    }
}
