<?php

namespace Ark4ne\JsonApi\Descriptors;

use Ark4ne\JsonApi\Descriptors\Relations\RelationMany;
use Ark4ne\JsonApi\Descriptors\Relations\RelationOne;
use Ark4ne\JsonApi\Descriptors\Values\{
    ValueArray,
    ValueBool,
    ValueDate,
    ValueFloat,
    ValueInteger,
    ValueMixed,
    ValueString
};
use Closure;

/**
 * @template T as \Illuminate\Database\Eloquent\Model
 */
trait Relations
{
    /**
     * @param class-string<\Ark4ne\JsonApi\Resources\JsonApiResource|\Ark4ne\JsonApi\Resources\JsonApiCollection> $for
     * @param null|string|Closure(T):mixed                                                                        $relation
     *
     * @return \Ark4ne\JsonApi\Descriptors\Relations\RelationOne<T>
     */
    protected function one(string $for, null|string|Closure $relation = null): RelationOne
    {
        return new RelationOne($for, $relation);
    }

    /**
     * @param class-string<\Ark4ne\JsonApi\Resources\JsonApiResource|\Ark4ne\JsonApi\Resources\JsonApiCollection> $for
     * @param null|string|Closure(T):mixed                                                                        $relation
     *
     * @return \Ark4ne\JsonApi\Descriptors\Relations\RelationMany<T>
     */
    protected function many(string $for, null|string|Closure $relation = null): RelationMany
    {
        return new RelationMany($for, $relation);
    }
}
