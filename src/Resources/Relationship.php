<?php

namespace Ark4ne\JsonApi\Resources;

use Ark4ne\JsonApi\Support\Values;
use Ark4ne\JsonApi\Traits\HasRelationLoad;
use Closure;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Collection;

use function value;

/**
 * @template T as JsonApiResource|JsonApiCollection
 */
class Relationship implements Resourceable
{
    use HasRelationLoad;

    protected string $relation;

    protected bool $asCollection = false;

    protected ?bool $whenIncluded = null;

    /**
     * @param class-string<T> $resource
     * @param Closure $value
     * @param Closure|null $links
     * @param Closure|null $meta
     */
    public function __construct(
        protected string   $resource,
        protected Closure  $value,
        protected ?Closure $links = null,
        protected ?Closure $meta = null
    ) {
    }

    /**
     * Re-set value for relation
     *
     * @param Closure $value
     *
     * @return $this
     */
    public function withValue(Closure $value): self
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Set callback for links for relation
     *
     * @param Closure $links
     *
     * @return $this
     */
    public function withLinks(Closure $links): self
    {
        $this->links = $links;

        return $this;
    }

    /**
     * Set callback for meta for relation
     *
     * @param Closure $meta
     *
     * @return $this
     */
    public function withMeta(Closure $meta): self
    {
        $this->meta = $meta;

        return $this;
    }

    /**
     * Use resource as collection.
     * Instantiate via ::collection insteadof ::make
     *
     * @return $this
     */
    public function asCollection(): self
    {
        $this->asCollection = true;

        return $this;
    }

    /**
     * Only load value if relation is included
     *
     * @param bool|null $whenIncluded
     * @return $this
     */
    public function whenIncluded(null|bool $whenIncluded = null): static
    {
        if ($whenIncluded === null) {
            $this->whenIncluded ??= true;
        } else {
            $this->whenIncluded = $whenIncluded;
        }

        return $this;
    }

    /**
     * Return class-string of resource
     *
     * @return class-string<T>
     */
    public function getResource(): string
    {
        return $this->resource;
    }

    /**
     * Set relation name for resource
     *
     * @param string $relation
     *
     * @return $this
     */
    public function forRelation(string $relation): self
    {
        $this->relation = $relation;

        return $this;
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param bool $included
     *
     * @return array{data?: array{data?:mixed, links?:mixed, meta?:mixed}, included?: mixed, with?: mixed}
     */
    public function toArray(mixed $request, bool $included = true): array
    {
        $value = $this->whenIncluded && !$included
            ? null
            : value($this->value);
        $value ??= new MissingValue;

        if ($this->asCollection && !is_subclass_of($this->resource, ResourceCollection::class)) {
            $resource = $this->resource::collection($value);
        } else {
            $resource = new $this->resource($value);
        }

        $data = [
            'data' => [],
            'links' => value($this->links, $resource),
            'meta' => value($this->meta, $resource),
        ];

        if (Values::isMissing($resource)) {
            return [
                'data' => array_filter($data)
            ];
        }

        if (is_object($resource) && method_exists($resource, 'toArray')) {
            $datum = $resource->toArray($request, $included);
        } else {
            $datum = (new Collection($resource))->toArray();
        }

        $includes = [];

        if ($resource instanceof ResourceCollection) {
            foreach ($datum as $value) {
                $data['data'][] = [
                    'type' => $value['type'],
                    'id' => $value['id']
                ];
                if ($included) {
                    $includes[] = $value;
                }
            }
        } elseif ($resource instanceof JsonResource) {
            $data['data'] = [
                'type' => $datum['type'],
                'id' => $datum['id']
            ];
            if ($included) {
                $includes[] = $datum;
            }
        } else { // @phpstan-ignore-line
            $data['data'] = $datum;
        }

        return array_filter([
            'data' => array_filter($data),
            'included' => $includes,
            'with' => is_object($resource) && method_exists($resource, 'with')
                ? $resource->with($request)
                : null
        ]);
    }
}
