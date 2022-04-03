<?php

namespace Ark4ne\JsonApi\Resource;

use Illuminate\Http\Resources\Json\ResourceCollection;

class JsonApiCollection extends ResourceCollection
{
    public $collects;

    /**
     * Create a new anonymous resource collection.
     *
     * @param mixed  $resource
     * @param string $collects
     *
     * @return void
     */
    public function __construct($resource, $collects)
    {
        $this->collects = $collects;

        parent::__construct($resource);
    }

    public function toArray($request, bool $minimal = false)
    {
        $data = [];

        foreach ($this->collection as $resource) {
            $data[] = $resource->toArray($request, $minimal);

            if (!$minimal) {
                $with = $resource->with($request);
                foreach ($with as $key => $value) {
                    $this->with[$key] = array_merge(
                        $this->with[$key] ?? [],
                        is_array($value) ? $value : [$value]
                    );
                }
            }
        }

        return $data;
    }

    public function with($request)
    {
        return array_filter(array_map(
            static fn($value) => is_array($value)
                ? array_unique($value, SORT_REGULAR)
                : $value,
            $this->with
        ));
    }
}
