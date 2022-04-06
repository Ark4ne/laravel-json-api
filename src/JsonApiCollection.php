<?php

namespace Ark4ne\JsonApi\Resource;

use Ark4ne\JsonApi\Resource\Concerns\AsRelationship;
use Ark4ne\JsonApi\Resource\Support\With;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

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

        $base = collect($this->with)->toArray();
        foreach ($this->collection as $resource) {
            $data[] = $resource->toArray($request, $minimal);

            if ($resource instanceof JsonResource) {
                $with = collect($resource->with($request))->toArray();

                if ($minimal) {
                    unset($with['included']);
                }

                $base = With::merge($base, $with);
            }
        }
        $this->with = $base;

        return $data;
    }

    public function with($request)
    {
        return With::wash($this->with);
    }
}
