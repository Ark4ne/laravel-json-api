<?php

namespace Ark4ne\JsonApi\Resource;

use Ark4ne\JsonApi\Resource\Concerns\AsRelationship;
use Illuminate\Http\Resources\Json\ResourceCollection;

class JsonApiCollection extends ResourceCollection implements Resourceable
{
    use AsRelationship;

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

    public function toArray($request, bool $minimal = false): array
    {
        $data = [];

        foreach ($this->collection as $resource) {
            $data[] = $resource->toArray($request, $minimal);

            if (!$minimal) {
                $with = $resource->with($request);
                foreach ($with as $key => $value) {
                    $this->with[$key] = array_merge(
                        $this->with[$key] ?? [],
                       collect($value)->all()
                    );
                }
            }
        }

        return $data;
    }

    public function with($request)
    {
        return collect($this->with)
            ->map(static fn($value) => is_iterable($value)
                ? collect($value)->unique()->all()
                : $value)
            ->filter()
            ->all();
    }
}
