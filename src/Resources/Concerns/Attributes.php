<?php

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Support\Fields;
use Illuminate\Http\Request;

trait Attributes
{
    use PrepareData;

    /**
     * @see https://jsonapi.org/format/#document-resource-object-attributes
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return iterable<string, \Closure|mixed>|iterable<array-key, \Ark4ne\JsonApi\Descriptors\Values\Value>
     *
     * ```
     * return [
     *     'name' => fn() => $this->name,
     *     // with laravel conditional attributes
     *     'secret' => fn() => $this->when($request->user()->isAdmin(), 'secret-value'),
     *     // with descriptors
     *     'email' => $this->string(),
     *     'age' => $this->integer()->whenInFields(),
     *     'secret' => $this->string(fn(Model $model) => $model->secret)->when($request->user()->isAdmin()),
     * ];
     * ```
     */
    protected function toAttributes(Request $request): iterable
    {
        if (is_object($this->resource) && method_exists($this->resource, 'toArray')) {
            return $this->resource->toArray();
        }
        if (is_array($this->resource)) {
            return $this->resource;
        }
        if (is_iterable($this->resource)) {
            return iterator_to_array($this->resource);
        }

        return [];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array<string, mixed>
     */
    private function requestedAttributes(Request $request): array
    {
        return Fields::through($this->toType($request), function () use ($request) {
            $attributes = $this->resolveAttributes($request);
            $attributes = $this->filter($attributes);

            $fields = Fields::get($request);

            $attributes = null === $fields
                ? $attributes
                : array_intersect_key($attributes, array_fill_keys($fields, true));

            return array_map('\value', $attributes);
        });
    }

    /**
     * @param Request $request
     * @return iterable<string, \Closure|mixed>|iterable<array-key, \Ark4ne\JsonApi\Descriptors\Values\Value>
     */
    private function prepareAttributes(Request $request): iterable
    {
        $attributes = $this->toAttributes($request);
        $attributes = $this->mergeValues($attributes);

        return $this->autoWhenHas($attributes, 'attributes');
    }

    /**
     * @param Request $request
     * @return iterable<string, \Closure|mixed>|iterable<array-key, \Ark4ne\JsonApi\Descriptors\Values\Value>
     */
    private function resolveAttributes(Request $request): iterable
    {
        $attributes = $this->prepareAttributes($request);

        return $this->resolveValues($request, $attributes);
    }
}
