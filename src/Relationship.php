<?php

namespace Ark4ne\JsonApi\Resource;

use Closure;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Http\Resources\PotentiallyMissing;

use function value;

/**
 * @template T
 */
class Relationship implements Resourceable
{
    protected string $relation;

    protected bool $asCollection = false;

    protected bool $whenIncluded = false;

    /**
     * @param class-string<T> $resource
     * @param \Closure        $value
     * @param \Closure|null   $links
     * @param \Closure|null   $meta
     */
    public function __construct(
        protected string $resource,
        protected Closure $value,
        protected ?Closure $links = null,
        protected ?Closure $meta = null
    ) {
    }

    /**
     * Set callback for links for relation
     *
     * @param \Closure $links
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
     * @param string $relation
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
     * @return $this
     */
    public function whenIncluded(): self
    {
        $this->whenIncluded = true;

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

    public function toArray($request, bool $included = true): array
    {
        $value = $this->whenIncluded && !$included
            ? new MissingValue
            : value($this->value);

        if ($this->asCollection && !is_a($this->resource, ResourceCollection::class)) {
            $resource = $this->resource::collection($value);
        } else {
            $resource = new $this->resource($value);
        }

        $data = [
            'data' => [],
            'links' => value($this->links, $resource),
            'meta' => value($this->meta, $resource),
        ];

        if ($this->isMissing($resource)) {
            return [
                'data' => array_filter($data)
            ];
        }

        if (is_object($resource) && method_exists($resource, 'toArray')) {
            $datum = $resource->toArray($request, $included);
        } else {
            $datum = collect($resource)->toArray();
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
        } else {
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

    private function isMissing($resource): bool
    {
        return ($resource instanceof PotentiallyMissing && $resource->isMissing())
            || ($resource instanceof JsonResource &&
                $resource->resource instanceof PotentiallyMissing &&
                $resource->resource->isMissing());
    }
}
