<?php

namespace Ark4ne\JsonApi\Resources;

use Ark4ne\JsonApi\Resources\Concerns;
use Ark4ne\JsonApi\Support\With;
use Illuminate\Http\Resources\Json\JsonResource;

use function collect;
use function tap;

/**
 * @template T
 * @property T $resource
 */
abstract class JsonApiResource extends JsonResource implements Resourceable
{
    use Concerns\Relationize,
        Concerns\Identifier,
        Concerns\Attributes,
        Concerns\Relationships,
        Concerns\Links,
        Concerns\Meta,
        Concerns\Schema,
        Concerns\ToResponse;

    /** @var T */
    public $resource;

    public function toArray($request, bool $included = true): array
    {
        $data = [
            'id' => $this->toIdentifier($request),
            'type' => $this->toType($request),
        ];

        if ($included) {
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
