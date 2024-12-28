<?php

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Descriptors\Describer;
use Ark4ne\JsonApi\Descriptors\Relations\RelationRaw;
use Ark4ne\JsonApi\Descriptors\Values\ValueMixed;
use Ark4ne\JsonApi\Resources\Relationship;
use Ark4ne\JsonApi\Support\Fields;
use Ark4ne\JsonApi\Support\Includes;
use Ark4ne\JsonApi\Support\Supported;
use Ark4ne\JsonApi\Support\Values;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MergeValue;
use Illuminate\Http\Resources\MissingValue;

trait ConditionallyLoadsAttributes
{
    /**
     * Retrieve a relationship if it has been present in fields.
     *
     * @template K
     *
     * @param \Illuminate\Http\Request $request
     * @param string $attribute
     * @param K $value
     *
     * @return \Illuminate\Http\Resources\MissingValue|K
     */
    protected function whenInFields(Request $request, string $attribute, mixed $value)
    {
        return $this->when(in_array($attribute, Fields::get($request, $this->toType($request)) ?? [], true), $value);
    }

    /**
     * Retrieve a relationship if it has been included.
     *
     * @template K
     *
     * @param \Illuminate\Http\Request $request
     * @param string $type
     * @param K $value
     *
     * @return \Illuminate\Http\Resources\MissingValue|K
     */
    protected function whenIncluded(Request $request, string $type, mixed $value)
    {
        return $this->when(Includes::include($request, $type), $value);
    }

    /**
     * @param bool|Closure():boolean $condition
     * @param iterable<array-key, mixed> $data
     *
     * @return \Illuminate\Http\Resources\MergeValue
     */
    protected function applyWhen(bool|Closure $condition, iterable $data): MergeValue
    {
        return new MergeValue(collect($data)->map(function ($raw) use ($condition) {
            if ($raw instanceof Describer) {
                $value = $raw;
            } elseif ($raw instanceof Relationship) {
                $value = RelationRaw::fromRelationship($raw);
            } else {
                $value = new ValueMixed(is_callable($raw) ? $raw : static fn () => $raw);
            }

            return $value->when(fn () => value($condition));
        }));
    }

    /**
     * @override JsonResource::whenHas
     * Support none Model resource
     *
     * Retrieve an attribute if it exists on the resource.
     *
     * @param string $attribute
     * @param mixed $value
     * @param mixed $default
     * @return \Illuminate\Http\Resources\MissingValue|mixed
     */
    public function whenHas($attribute, $value = null, $default = null)
    {
        if (func_num_args() < 3) {
            $default = new MissingValue;
        }

        if (!Values::hasAttribute($this->resource, $attribute)) {
            return value($default);
        }

        return func_num_args() === 1
            ? Values::getAttribute($this->resource, $attribute)
            : value($value, Values::getAttribute($this->resource, $attribute));
    }

    /**
     * @polyfill JsonResource::unless
     * @see https://github.com/laravel/framework/pull/45419/files
     *
     * Retrieve a value if the given "condition" is falsy.
     *
     * @param  bool  $condition
     * @param  mixed  $value
     * @param  mixed  $default
     * @return \Illuminate\Http\Resources\MissingValue|mixed
     */
    public function unless($condition, $value, $default = null)
    {
        if (Supported::$unless) {
            // @phpstan-ignore-next-line
            return parent::unless($condition, $value, $default);
        }

        $arguments = func_num_args() === 2 ? [$value] : [$value, $default];

        return $this->when(! $condition, ...$arguments);
    }
}
