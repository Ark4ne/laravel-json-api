<?php

namespace Ark4ne\JsonApi\Resource;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class JsonApiResource extends JsonResource
{
    use Concerns\Identifier,
        Concerns\Attributes,
        Concerns\Relationships,
        Concerns\Links,
        Concerns\Meta;

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
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
        $this->with['meta'] = array_merge($this->with['meta'] ?? [], (array)$this->toMeta($request));

        return array_filter(array_map(
            static fn($value) => is_array($value)
                ? array_unique($value, SORT_REGULAR)
                : $value,
            $this->with
        ));
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

    /**
     * @param Request $request
     */
    public function toResponse($request): JsonResponse
    {
        return parent
            ::toResponse($request)
            ->header('Content-type', 'application/vnd.api+json');
    }
}
