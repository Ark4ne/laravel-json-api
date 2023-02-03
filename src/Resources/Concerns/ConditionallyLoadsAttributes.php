<?php

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Descriptors\Relations\Relation;
use Ark4ne\JsonApi\Descriptors\Relations\RelationMissing;
use Ark4ne\JsonApi\Resources\Relationship;
use Ark4ne\JsonApi\Support\Fields;
use Ark4ne\JsonApi\Support\Includes;
use Ark4ne\JsonApi\Support\Supported;
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
     * @param bool $condition
     * @param iterable<array-key, mixed> $data
     *
     * @return \Illuminate\Http\Resources\MergeValue
     */
    protected function applyWhen(bool $condition, iterable $data): MergeValue
    {
        if ($condition) {
            return new MergeValue($data);
        }

        return new MergeValue(collect($data)->map(function ($raw) {
            if ($raw instanceof Relationship) {
                return RelationMissing::fromRelationship($raw);
            }

            if ($raw instanceof Relation) {
                return $raw->when(false);
            }

            return new MissingValue();
        }));
    }

    /**
     * @polyfill JsonResource::whenHas
     * @see https://github.com/laravel/framework/pull/45376/files
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
        if (Supported::$whenHas) {
            // @phpstan-ignore-next-line
            return parent::whenHas($attribute, $value, $default);
        }

        if (func_num_args() < 3) {
            $default = new MissingValue;
        }

        if (!array_key_exists($attribute, $this->resource->getAttributes())) {
            return value($default);
        }

        return func_num_args() === 1
            ? $this->resource->{$attribute}
            : value($value, $this->resource->{$attribute});
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
        if (Supported::$whenHas) {
            // @phpstan-ignore-next-line
            return parent::unless($condition, $value, $default);
        }

        $arguments = func_num_args() === 2 ? [$value] : [$value, $default];

        return $this->when(! $condition, ...$arguments);
    }
}
