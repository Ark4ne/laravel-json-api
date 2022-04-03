<?php

namespace Ark4ne\JsonApi\Resource\Concerns;

use Ark4ne\JsonApi\Resource\Support\Fields;
use Illuminate\Http\Request;

trait Attributes
{
    /**
     * @see https://jsonapi.org/format/#document-resource-object-attributes
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array<string, mixed>
     *
     * ```
     * return [
     *     'name' => $this->name,
     *     // with laravel conditional attributes
     *     'secret' => $this->when($request->user()->isAdmin(), 'secret-value'),
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
     * @return array
     */
    private function requestedAttributes(Request $request): array
    {
        $attributes = $this->filter((array)$this->toAttributes($request));

        $fields = Fields::get($request, $this->toType($request));

        $attributes = empty($fields)
            ? $attributes
            : array_intersect_key($attributes, array_fill_keys($fields, true));

        return array_map('\value', $attributes);
    }
}
