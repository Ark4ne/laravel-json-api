<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors;

use Ark4ne\JsonApi\Descriptors\Values\{Value,
    ValueArray,
    ValueBool,
    ValueDate,
    ValueDateOnly,
    ValueDecimal,
    ValueEnum,
    ValueFloat,
    ValueInteger,
    ValueJson,
    ValueMixed,
    ValueString,
    ValueStruct,
    ValueTime,
    ValueUlid,
    ValueUuid};
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
    protected function float(string|Closure|null $attribute = null): ValueFloat
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
     * A calendar date, `Y-m-d` unless `format()` says otherwise.
     *
     * @param null|string|Closure(T):(DateTimeInterface|string|int|null) $attribute
     *
     * @return ValueDateOnly<T>
     */
    protected function dateOnly(string|Closure|null $attribute = null): ValueDateOnly
    {
        return new ValueDateOnly($attribute);
    }

    /**
     * A time of day, `H:i:s` unless `format()` says otherwise.
     *
     * @param null|string|Closure(T):(DateTimeInterface|string|int|null) $attribute
     *
     * @return ValueTime<T>
     */
    protected function time(string|Closure|null $attribute = null): ValueTime
    {
        return new ValueTime($attribute);
    }

    /**
     * An exact decimal number rendered as a string, `scale()` decimals.
     *
     * @param null|string|Closure(T):(string|int|float|null) $attribute
     *
     * @return ValueDecimal<T>
     */
    protected function decimal(string|Closure|null $attribute = null): ValueDecimal
    {
        return new ValueDecimal($attribute);
    }

    /**
     * @param null|string|Closure(T):mixed $attribute
     *
     * @return ValueUuid<T>
     */
    protected function uuid(string|Closure|null $attribute = null): ValueUuid
    {
        return new ValueUuid($attribute);
    }

    /**
     * @param null|string|Closure(T):mixed $attribute
     *
     * @return ValueUlid<T>
     */
    protected function ulid(string|Closure|null $attribute = null): ValueUlid
    {
        return new ValueUlid($attribute);
    }

    /**
     * A JSON document held as a string, rendered decoded.
     *
     * @param null|string|Closure(T):mixed $attribute
     *
     * @return ValueJson<T>
     */
    protected function json(string|Closure|null $attribute = null): ValueJson
    {
        return new ValueJson($attribute);
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
