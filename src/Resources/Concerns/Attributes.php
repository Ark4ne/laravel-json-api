<?php

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Support\Fields;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

use function value;

trait Attributes
{
    /**
     * @see https://jsonapi.org/format/#document-resource-object-attributes
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return iterable<string, Closure|mixed>
     *
     * ```
     * return [
     *     'name' => fn() => $this->name,
     *     // with laravel conditional attributes
     *     'secret' => fn() => $this->when($request->user()->isAdmin(), 'secret-value'),
     * ];
     * ```
     */
    protected function toAttributes(Request $request): iterable
    {
        return $this->resource->toArray();
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array<string, mixed>
     */
    private function requestedAttributes(Request $request): array
    {
        $attributes = (new Collection($this->toAttributes($request)))
            ->map(fn($value) => value($value))
            ->toArray();

        $attributes = $this->filter($attributes);

        $fields = Fields::get($request, $this->toType($request));

        $attributes = null === $fields
            ? $attributes
            : array_intersect_key($attributes, array_fill_keys($fields, true));

        return array_map('\value', $attributes);
    }
}
