<?php

namespace Ark4ne\JsonApi\Resource;

use Ark4ne\JsonApi\Resource\Concerns;
use Ark4ne\JsonApi\Resource\Support\With;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

class JsonApiCollection extends ResourceCollection implements Resourceable
{
    use Concerns\Relationize,
        Concerns\Schema,
        Concerns\ToResponse;

    public $collects;

    /**
     * Create a new anonymous resource collection.
     *
     * @param mixed             $resource
     * @param null|class-string $collects
     *
     * @return void
     */
    final public function __construct($resource, ?string $collects = null)
    {
        $this->collects = $collects ?: $this->collects;

        parent::__construct($resource);
    }

    public function toArray($request, bool $included = true): array
    {
        $data = [];

        $base = collect($this->with)->toArray();
        foreach ($this->collection as $resource) {
            $data[] = $resource->toArray($request, $included);

            if ($resource instanceof JsonResource) {
                $with = collect($resource->with($request))->toArray();

                if (!$included) {
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
