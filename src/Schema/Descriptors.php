<?php

namespace Ark4ne\JsonApi\Schema;

use Ark4ne\JsonApi\Descriptors\Values\ValueArray;
use Ark4ne\JsonApi\Descriptors\Values\ValueBool;
use Ark4ne\JsonApi\Descriptors\Values\ValueDate;
use Ark4ne\JsonApi\Descriptors\Values\ValueFloat;
use Ark4ne\JsonApi\Descriptors\Values\ValueInteger;
use Ark4ne\JsonApi\Descriptors\Values\ValueMixed;
use Ark4ne\JsonApi\Descriptors\Values\ValueString;
use Ark4ne\JsonApi\Descriptors\Relations\RelationMany;
use Ark4ne\JsonApi\Descriptors\Relations\RelationOne;
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
    public function bool(null|string|Closure $attribute = null): ValueBool
    {
        return new ValueBool($attribute);
    }

    /**
     * @param null|string|Closure(T):int $attribute
     *
     * @return \Ark4ne\JsonApi\Descriptors\Values\ValueInteger<T>
     */
    public function integer(null|string|Closure $attribute = null): ValueInteger
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
    public function string(null|string|Closure $attribute = null): ValueString
    {
        return new ValueString($attribute);
    }

    /**
     * @param null|string|Closure(T):\DateTimeInterface $attribute
     *
     * @return \Ark4ne\JsonApi\Descriptors\Values\ValueDate<T>
     */
    public function date(null|string|Closure $attribute = null): ValueDate
    {
        return new ValueDate($attribute);
    }

    /**
     * @param null|string|Closure(T):array $attribute
     *
     * @return \Ark4ne\JsonApi\Descriptors\Values\ValueArray<T>
     */
    public function array(null|string|Closure $attribute = null): ValueArray
    {
        return new ValueArray($attribute);
    }

    /**
     * @param null|string|Closure(T):object $attribute
     *
     * @return \Ark4ne\JsonApi\Descriptors\Values\ValueMixed<T>
     */
    public function object(null|string|Closure $attribute = null): ValueMixed
    {
        return new ValueMixed($attribute);
    }

    /**
     * @param class-string<\Ark4ne\JsonApi\Schema\JsonApiSchema> $for
     * @param null|string|Closure(T):mixed                       $relation
     *
     * @return \Ark4ne\JsonApi\Descriptors\Relations\RelationOne<T>
     */
    public function one(string $for, null|string|Closure $relation = null): RelationOne
    {
        return new RelationOne($for, $relation);
    }

    /**
     * @param class-string<\Ark4ne\JsonApi\Schema\JsonApiSchema> $for
     * @param null|string|Closure(T):mixed                       $relation
     *
     * @return \Ark4ne\JsonApi\Descriptors\Relations\RelationMany<T>
     */
    public function many(string $for, null|string|Closure $relation = null): RelationMany
    {
        return new RelationMany($for, $relation);
    }
}
