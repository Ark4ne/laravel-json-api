<?php

namespace Ark4ne\JsonApi\Resources;

use Ark4ne\JsonApi\Descriptors;
use Ark4ne\JsonApi\Resources\Concerns;
use Ark4ne\JsonApi\Support\Arr;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @template T
 * @property T $resource
 */
abstract class JsonApiResource extends JsonResource implements Resourceable
{
    use Descriptors\Relations,
        Descriptors\Values,
        Concerns\Relationize,
        Concerns\Identifier,
        Concerns\Attributes,
        Concerns\ConditionallyLoadsAttributes,
        Concerns\Relationships,
        Concerns\Links,
        Concerns\Meta,
        Concerns\Schema,
        Concerns\ToResponse;

    /** @var T */
    public $resource;

    /**
     * @param \Illuminate\Http\Request $request
     * @param bool                     $included
     *
     * @return array{id: int|string, type: string, attributes?:array<string, string>, relationships?:array<string, mixed>, links?:mixed, meta?:mixed}
     */
    public function toArray(mixed $request, bool $included = true): array
    {
        $loads = $this->requestedRelationshipsLoad($request);

        if (!empty($loads) && $this->resource instanceof Model) {
            $this->resource->loadMissing($loads);
        }

        $data = [
            'id' => $this->toIdentifier($request),
            'type' => $this->toType($request),
        ];

        if ($included) {
            $data += Arr::toArray(array_filter([
                'attributes' => $this->requestedAttributes($request),
                'relationships' => $this->requestedRelationships($request),
                'links' => $this->requestedLinks($request),
                'meta' => $this->requestedResourceMeta($request)
            ]));
        }

        return $data;
    }

    /**
     * @param \Illuminate\Http\Request|mixed $request
     *
     * @return array<mixed>
     */
    public function with($request): array
    {
        $with = $this->with;

        if ($meta = $this->requestedMeta($request)) {
            $with = Arr::merge($with, ['meta' => $meta]);
        }

        return Arr::wash($with);
    }

    /**
     * @param mixed $resource
     *
     * @return JsonApiCollection<static>
     */
    public static function collection($resource): JsonApiCollection
    {
        /** @var \Ark4ne\JsonApi\Resources\JsonApiCollection<static> $collection */
        $collection = new class($resource, static::class) extends JsonApiCollection {
            public ?bool $preserveKeys = null;
        };

        if (property_exists(static::class, 'preserveKeys')) {
            // @phpstan-ignore-next-line
            $collection->preserveKeys = (new static([]))->preserveKeys;
        }

        return $collection;
    }
}
