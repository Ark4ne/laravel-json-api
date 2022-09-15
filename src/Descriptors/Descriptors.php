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
trait Descriptors
{
    /**
     * @param null|string|Closure(T):bool $attribute
     *
     * @return \Ark4ne\JsonApi\Descriptors\Values\ValueBool<T>
     */
    protected function bool(null|string|Closure $attribute = null): ValueBool
    {
        return new ValueBool($attribute);
    }

    /**
     * @param null|string|Closure(T):int $attribute
     *
     * @return \Ark4ne\JsonApi\Descriptors\Values\ValueInteger<T>
     */
    protected function integer(null|string|Closure $attribute = null): ValueInteger
    {
        return new ValueInteger($attribute);
    }

    /**
     * @param null|string|Closure(T):float $attribute
     *
     * @return \Ark4ne\JsonApi\Descriptors\Values\ValueFloat<T>
     */
    public function float(null|string|Closure $attribute = null): ValueFloat
    {
        return new ValueFloat($attribute);
    }

    /**
     * @param null|string|Closure(T):string $attribute
     *
     * @return \Ark4ne\JsonApi\Descriptors\Values\ValueString<T>
     */
    protected function string(null|string|Closure $attribute = null): ValueString
    {
        return new ValueString($attribute);
    }

    /**
     * @param null|string|Closure(T):\DateTimeInterface $attribute
     *
     * @return \Ark4ne\JsonApi\Descriptors\Values\ValueDate<T>
     */
    protected function date(null|string|Closure $attribute = null): ValueDate
    {
        return new ValueDate($attribute);
    }

    /**
     * @param null|string|Closure(T):array<mixed> $attribute
     *
     * @return \Ark4ne\JsonApi\Descriptors\Values\ValueArray<T>
     */
    protected function array(null|string|Closure $attribute = null): ValueArray
    {
        return new ValueArray($attribute);
    }

    /**
     * @param null|string|Closure(T):object $attribute
     *
     * @return \Ark4ne\JsonApi\Descriptors\Values\ValueMixed<T>
     */
    protected function mixed(null|string|Closure $attribute = null): ValueMixed
    {
        return new ValueMixed($attribute);
    }

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
