<?php

namespace Ark4ne\JsonApi\Descriptors\Values;

use Ark4ne\JsonApi\Descriptors\Resolver;
use Ark4ne\JsonApi\Support\Values;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * @template T
 * @extends Value<T>
 */
class ValueStruct extends Value
{
    use Resolver;

    protected mixed $resource;

    public function __construct(Closure $values)
    {
        parent::__construct($values);
    }

    public function resolveFor(Request $request, mixed $model, string $field): mixed
    {
        $this->resource = $model;

        return parent::resolveFor($request, $model, $field);
    }

    /**
     * @param mixed $of
     * @param Request $request
     *
     * @return array<array-key, mixed>
     */
    public function value(mixed $of, Request $request): array
    {
        $attributes = Values::mergeValues($this->resolveValues($request, Values::mergeValues($of)));

        return (new Collection($attributes))->toArray();
    }
}
