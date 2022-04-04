<?php

namespace Ark4ne\JsonApi\Resource;

use Closure;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\PotentiallyMissing;

use function value;

class Relationship implements Resourceable
{
    public function __construct(
        protected Resourceable $resource,
        protected iterable|Closure $links = [],
        protected iterable|Closure $meta = []
    ) {
    }

    public function withLinks(iterable|Closure $links): self
    {
        $this->links = $links;

        return $this;
    }

    public function withMeta(iterable|Closure $meta): self
    {
        $this->meta = $meta;

        return $this;
    }

    public function toArray($request, bool $minimal = false): array
    {
        $data = [
            'data' => [],
            'links' => value($this->links, $this->resource),
            'meta' => value($this->meta, $this->resource),
        ];

        $resource = value($this->resource);

        if ($this->isMissing($resource)) {
            return [
                'data' => array_filter($data)
            ];
        }

        $included = [];

        $datum = $resource->toArray($request, $minimal);

        if ($resource instanceof JsonApiCollection) {
            foreach ($datum as $value) {
                $data['data'][] = [
                    'type' => $value['type'],
                    'id' => $value['id']
                ];
                if (!$minimal) {
                    $included[] = $value;
                }
            }
        } else {
            $data['data'] = [
                'type' => $datum['type'],
                'id' => $datum['id']
            ];
            if (!$minimal) {
                $included[] = $datum;
            }
        }

        return array_filter([
            'data' => array_filter($data),
            'included' => $included,
            'with' => $resource->with($request)
        ]);
    }

    private function isMissing($resource): bool
    {
        return ($resource instanceof PotentiallyMissing && $resource->isMissing())
            || ($resource instanceof JsonResource &&
                $resource->resource instanceof PotentiallyMissing &&
                $resource->isMissing());
    }
}
