<?php

namespace Ark4ne\JsonApi\Resources;

use Ark4ne\JsonApi\Support\Arr;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * @template T as JsonApiResource
 */
class JsonApiCollection extends ResourceCollection implements Resourceable
{
    use Concerns\Relationize,
        Concerns\SchemaCollection,
        Concerns\ToResponse;

    /**
     * @var class-string<T>
     */
    public $collects;

    /**
     * Create a new anonymous resource collection.
     *
     * @param mixed $resource
     * @param null|class-string<T> $collects
     *
     * @return void
     */
    public function __construct($resource, ?string $collects = null)
    {
        $this->collects = $collects ?: $this->collects;

        parent::__construct($resource);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param bool $included
     *
     * @return array<int, array{id: int|string}>
     */
    public function toArray(mixed $request, bool $included = true): array
    {
        $collection = collect($this->collection);

        if ($collection->every(static fn($value) => $value instanceof JsonApiResource)) {
            // @phpstan-ignore-next-line
            $loads = array_merge(...$collection->map->requestedRelationshipsLoad($request));

            if (!empty($loads)) {
                // @phpstan-ignore-next-line
                $resources = $collection->map->resource;

                if ($resources->every(static fn($resource) => $resource instanceof Model)) {
                    (new Collection($resources))->loadMissing($loads);
                }
            }
        }

        $data = [];

        $base = $this->with;
        foreach ($this->collection as $resource) {
            $data[] = $resource->toArray($request, $included);

            if ($resource instanceof JsonResource) {
                $with = $resource->with($request);

                if (!$included) {
                    unset($with['included']);
                }

                $base = Arr::merge($base, $with);
            }
        }
        $this->with = $base;

        return $data;
    }

    /**
     * @param \Illuminate\Http\Request|mixed $request
     *
     * @return array<mixed>
     */
    public function with($request): array
    {
        return Arr::wash($this->with);
    }
}
