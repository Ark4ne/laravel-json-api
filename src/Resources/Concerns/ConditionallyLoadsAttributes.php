<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Descriptors\Describer;
use Ark4ne\JsonApi\Descriptors\Relations\RelationRaw;
use Ark4ne\JsonApi\Descriptors\Values\ValueMixed;
use Ark4ne\JsonApi\Resources\Relationship;
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
        $context = $this->renderingContext;

        return $this->when(
            $context !== null && $context->fields->requests($this->toType($request), $attribute),
            $value,
        );
    }

    /**
     * Retrieve a value if a relationship of the resource is included (`include`). Not to be confused with
     * `whenIncluded()` of the relationships.
     *
     * @template K
     *
     * @param \Illuminate\Http\Request $request
     * @param string $relationship
     * @param K $value
     *
     * @return \Illuminate\Http\Resources\MissingValue|K
     */
    protected function whenInIncludes(Request $request, string $relationship, mixed $value)
    {
        return $this->when($this->renderingContext?->includes($relationship) ?? false, $value);
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
                $value = new ValueMixed(is_callable($raw) ? $raw : static fn() => $raw);
            }

            return $value->when(static fn() => value($condition));
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
            $default = new MissingValue();
        }

        if (!Values::hasAttribute($this->resource, $attribute)) {
            return value($default);
        }

        return func_num_args() === 1
            ? Values::getAttribute($this->resource, $attribute)
            : value($value, Values::getAttribute($this->resource, $attribute));
    }
}
