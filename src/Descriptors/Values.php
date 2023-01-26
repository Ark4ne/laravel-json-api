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
 * @template T
 */
trait Values
{
    /**
     * @param null|string|Closure(T):mixed $attribute
     *
     * @return \Ark4ne\JsonApi\Descriptors\Values\ValueBool<T>
     */
    protected function bool(null|string|Closure $attribute = null): ValueBool
    {
        return new ValueBool($attribute);
    }

    /**
     * @param null|string|Closure(T):mixed $attribute
     *
     * @return \Ark4ne\JsonApi\Descriptors\Values\ValueInteger<T>
     */
    protected function integer(null|string|Closure $attribute = null): ValueInteger
    {
        return new ValueInteger($attribute);
    }

    /**
     * @param null|string|Closure(T):mixed $attribute
     *
     * @return \Ark4ne\JsonApi\Descriptors\Values\ValueFloat<T>
     */
    public function float(null|string|Closure $attribute = null): ValueFloat
    {
        return new ValueFloat($attribute);
    }

    /**
     * @param null|string|Closure(T):mixed $attribute
     *
     * @return \Ark4ne\JsonApi\Descriptors\Values\ValueString<T>
     */
    protected function string(null|string|Closure $attribute = null): ValueString
    {
        return new ValueString($attribute);
    }

    /**
     * @param null|string|Closure(T):(\DateTimeInterface|string|int|null) $attribute
     *
     * @return \Ark4ne\JsonApi\Descriptors\Values\ValueDate<T>
     */
    protected function date(null|string|Closure $attribute = null): ValueDate
    {
        return new ValueDate($attribute);
    }

    /**
     * @param null|string|Closure(T):(array<mixed>|null) $attribute
     *
     * @return \Ark4ne\JsonApi\Descriptors\Values\ValueArray<T>
     */
    protected function array(null|string|Closure $attribute = null): ValueArray
    {
        return new ValueArray($attribute);
    }

    /**
     * @param null|string|Closure(T):mixed $attribute
     *
     * @return \Ark4ne\JsonApi\Descriptors\Values\ValueMixed<T>
     */
    protected function mixed(null|string|Closure $attribute = null): ValueMixed
    {
        return new ValueMixed($attribute);
    }
}
