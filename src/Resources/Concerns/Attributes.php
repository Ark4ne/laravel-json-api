<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources\Concerns;

use Closure;
use Illuminate\Http\Request;

trait Attributes
{
    use PrepareData;

    /**
     * @see https://jsonapi.org/format/#document-resource-object-attributes
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return iterable<array-key, mixed|Closure|\Ark4ne\JsonApi\Descriptors\Values\Value>
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
    public function toAttributes(Request $request): iterable
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
}
