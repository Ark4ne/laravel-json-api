<?php

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Descriptors\Relations\Relation;
use Ark4ne\JsonApi\Descriptors\Relations\RelationMissing;
use Ark4ne\JsonApi\Resources\Relationship;
use Ark4ne\JsonApi\Support\Fields;
use Ark4ne\JsonApi\Support\Includes;
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
     * @param string                   $attribute
     * @param K                        $value
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
     * @param string                   $type
     * @param K                        $value
     *
     * @return \Illuminate\Http\Resources\MissingValue|K
     */
    protected function whenIncluded(Request $request, string $type, mixed $value)
    {
        return $this->when(Includes::include($request, $type), $value);
    }

    /**
     * @param bool                       $condition
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
}
