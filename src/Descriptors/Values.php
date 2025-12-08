<?php

namespace Ark4ne\JsonApi\Descriptors;

use Ark4ne\JsonApi\Descriptors\Values\{Value,
    ValueArray,
    ValueBool,
    ValueDate,
    ValueEnum,
    ValueFloat,
    ValueInteger,
    ValueMixed,
    ValueString,
    ValueStruct};
use Closure;

/**
 * @template T
 */
trait Values
{
    /**
     * @param null|string|Closure(T):mixed $attribute
     *
     * @return ValueBool<T>
     */
    protected function bool(null|string|Closure $attribute = null): ValueBool
    {
        return new ValueBool($attribute);
    }

    /**
     * @param null|string|Closure(T):mixed $attribute
     *
     * @return ValueInteger<T>
     */
    protected function integer(null|string|Closure $attribute = null): ValueInteger
    {
        return new ValueInteger($attribute);
    }

    /**
     * @param null|string|Closure(T):mixed $attribute
     *
     * @return ValueFloat<T>
     */
    public function float(null|string|Closure $attribute = null): ValueFloat
    {
        return new ValueFloat($attribute);
    }

    /**
     * @param null|string|Closure(T):mixed $attribute
     *
     * @return ValueString<T>
     */
    protected function string(null|string|Closure $attribute = null): ValueString
    {
        return new ValueString($attribute);
    }

    /**
     * @param null|string|Closure(T):(\DateTimeInterface|string|int|null) $attribute
     *
     * @return ValueDate<T>
     */
    protected function date(null|string|Closure $attribute = null): ValueDate
    {
        return new ValueDate($attribute);
    }

    /**
     * @param null|string|Closure(T):(array<mixed>|null) $attribute
     *
     * @return ValueArray<T, mixed>
     */
    protected function array(null|string|Closure $attribute = null): ValueArray
    {
        return new ValueArray($attribute);
    }

    /**
     * @param null|string|Closure(T):mixed $attribute
     *
     * @return ValueMixed<T>
     */
    protected function mixed(null|string|Closure $attribute = null): ValueMixed
    {
        return new ValueMixed($attribute);
    }

    /**
     * @param null|string|Closure(T):mixed $attribute
     *
     * @return ValueEnum<T, mixed>
     */
    protected function enum(null|string|Closure $attribute = null): ValueEnum
    {
        return new ValueEnum($attribute);
    }

    /**
     * @param Closure(T):iterable<string, mixed|Closure|Value> $attribute
     *
     * @return ValueStruct<T>
     */
    protected function struct(Closure $attribute): ValueStruct
    {
        return new ValueStruct($attribute);
    }

    /**
     * @param Value<T> $type
     * @param null|string|Closure(T):(array<mixed>|null) $attribute
     * @return ValueArray<T, mixed>
     */
    protected function arrayOf(Value $type, null|string|Closure $attribute = null): ValueArray
    {
        return (new ValueArray($attribute))->of($type);
    }
}
