<?php

namespace Ark4ne\JsonApi\Resource;

use Ark4ne\JsonApi\Resource\Concerns\AsRelationship;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Arr;

class JsonApiCollection extends ResourceCollection implements Resourceable
{
    use AsRelationship;

    public $collects;

    /**
     * Create a new anonymous resource collection.
     *
     * @param mixed        $resource
     * @param class-string $collects
     *
     * @return void
     */
    public function __construct($resource, $collects)
    {
        $this->collects = $collects;

        parent::__construct($resource);
    }

    public function toArray($request, bool $minimal = false): array
    {
        $data = [];

        $this->with = collect($this->with)->toArray();
        foreach ($this->collection as $resource) {
            $data[] = $resource->toArray($request, $minimal);

            if ($resource instanceof JsonResource) {
                $with = collect($resource->with($request))->toArray();
                if ($minimal) {
                    unset($with['included']);
                }
                foreach ($with as $key => $value) {
                    $this->with[$key] = array_merge_recursive(
                        $this->with[$key] ?? [],
                        $value
                    );
                }
            }
        }

        return $data;
    }

    public function with($request)
    {
        return collect($this->with)
            ->map(static function ($value) {
                if (is_iterable($value)) {
                    $value = collect($value)->all();
                    $isAssoc = Arr::isAssoc($value);
                    $value = array_unique($value, SORT_REGULAR);
                    return $isAssoc ? $value : array_values($value);
                }
                return $value;
            })
            ->filter()
            ->toArray();
    }
}
