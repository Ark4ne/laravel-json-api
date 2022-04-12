<?php

namespace Ark4ne\JsonApi\Resource;

use Ark4ne\JsonApi\Resource\Support\With;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class JsonApiResource extends JsonResource implements Resourceable
{
    use Concerns\Relationize,
        Concerns\Identifier,
        Concerns\Attributes,
        Concerns\Relationships,
        Concerns\Links,
        Concerns\Meta,
        Concerns\ToResponse;

    public function toArray($request, bool $minimal = false): array
    {
        $data = [
            'id' => $this->toIdentifier($request),
            'type' => $this->toType($request),
        ];

        if (!$minimal) {
            $data += [
                'attributes' => $this->requestedAttributes($request),
                'relationships' => $this->requestedRelationships($request),
                'links' => $this->toLinks($request),
                'meta' => $this->toResourceMeta($request)
            ];
        }

        return array_filter($data);
    }

    public function with($request)
    {
        $with = collect($this->with);

        if ($meta = $this->toMeta($request)) {
            $with = With::merge($with, ['meta' => $meta]);
        }

        return With::wash($with);
    }

    /**
     * @param mixed $resource
     *
     * @return JsonApiCollection
     */
    public static function collection($resource): JsonApiCollection
    {
        return tap(new JsonApiCollection($resource, static::class),
            static function (JsonApiCollection $collection): void {
                if (property_exists(static::class, 'preserveKeys')) {
                    /** @phpstan-ignore-next-line */
                    $collection->preserveKeys = (new static([]))->preserveKeys === true;
                }
            });
    }
}
