<?php

declare(strict_types=1);

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
use DateTimeInterface;
use UnitEnum;

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
    protected function bool(string|Closure|null $attribute = null): ValueBool
    {
        return new ValueBool($attribute);
    }

    /**
     * @param null|string|Closure(T):mixed $attribute
     *
     * @return ValueInteger<T>
     */
    protected function integer(string|Closure|null $attribute = null): ValueInteger
    {
        return new ValueInteger($attribute);
    }

    /**
     * @param null|string|Closure(T):mixed $attribute
     *
     * @return ValueFloat<T>
     */
    public function float(string|Closure|null $attribute = null): ValueFloat
    {
        return new ValueFloat($attribute);
    }

    /**
     * @param null|string|Closure(T):mixed $attribute
     *
     * @return ValueString<T>
     */
    protected function string(string|Closure|null $attribute = null): ValueString
    {
        return new ValueString($attribute);
    }

    /**
     * @param null|string|Closure(T):(DateTimeInterface|string|int|null) $attribute
     *
     * @return ValueDate<T>
     */
    protected function date(string|Closure|null $attribute = null): ValueDate
    {
        return new ValueDate($attribute);
    }

    /**
     * @param null|string|Closure(T):(array<mixed>|null) $attribute
     *
     * @return ValueArray<T, mixed>
     */
    protected function array(string|Closure|null $attribute = null): ValueArray
    {
        return new ValueArray($attribute);
    }

    /**
     * @param null|string|Closure(T):mixed $attribute
     *
     * @return ValueMixed<T>
     */
    protected function mixed(string|Closure|null $attribute = null): ValueMixed
    {
        return new ValueMixed($attribute);
    }

    /**
     * @param null|string|Closure(T):mixed $attribute
     *
     * @return ValueEnum<T, UnitEnum>
     */
    protected function enum(string|Closure|null $attribute = null): ValueEnum
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
    protected function arrayOf(Value $type, string|Closure|null $attribute = null): ValueArray
    {
        return (new ValueArray($attribute))->of($type);
    }
}
