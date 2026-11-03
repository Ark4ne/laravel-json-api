<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors;

use Ark4ne\JsonApi\Descriptors\Values\{Value,
    ValueArray,
    ValueBool,
    ValueDate,
    ValueDateTime,
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
        /** @var ValueBool<T> */
        return new ValueBool($attribute);
    }

    /**
     * @param null|string|Closure(T):mixed $attribute
     *
     * @return ValueInteger<T>
     */
    protected function integer(string|Closure|null $attribute = null): ValueInteger
    {
        /** @var ValueInteger<T> */
        return new ValueInteger($attribute);
    }

    /**
     * @param null|string|Closure(T):mixed $attribute
     *
     * @return ValueFloat<T>
     */
    protected function float(string|Closure|null $attribute = null): ValueFloat
    {
        /** @var ValueFloat<T> */
        return new ValueFloat($attribute);
    }

    /**
     * @param null|string|Closure(T):mixed $attribute
     *
     * @return ValueString<T>
     */
    protected function string(string|Closure|null $attribute = null): ValueString
    {
        /** @var ValueString<T> */
        return new ValueString($attribute);
    }

    /**
     * A calendar date, `Y-m-d` unless `format()` says otherwise.
     *
     * @param null|string|Closure(T):(DateTimeInterface|string|int|null) $attribute
     *
     * @return ValueDate<T>
     */
    protected function date(string|Closure|null $attribute = null): ValueDate
    {
        /** @var ValueDate<T> */
        return new ValueDate($attribute);
    }

    /**
     * A date and time, in the configured format (`describer.datetime`) unless `format()` says otherwise.
     *
     * @param null|string|Closure(T):(DateTimeInterface|string|int|null) $attribute
     *
     * @return ValueDateTime<T>
     */
    protected function datetime(string|Closure|null $attribute = null): ValueDateTime
    {
        /** @var ValueDateTime<T> */
        return new ValueDateTime($attribute);
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
        /** @var ValueTime<T> */
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
        /** @var ValueDecimal<T> */
        return new ValueDecimal($attribute);
    }

    /**
     * @param null|string|Closure(T):mixed $attribute
     *
     * @return ValueUuid<T>
     */
    protected function uuid(string|Closure|null $attribute = null): ValueUuid
    {
        /** @var ValueUuid<T> */
        return new ValueUuid($attribute);
    }

    /**
     * @param null|string|Closure(T):mixed $attribute
     *
     * @return ValueUlid<T>
     */
    protected function ulid(string|Closure|null $attribute = null): ValueUlid
    {
        /** @var ValueUlid<T> */
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
        /** @var ValueJson<T> */
        return new ValueJson($attribute);
    }

    /**
     * @param null|string|Closure(T):(array<mixed>|null) $attribute
     *
     * @return ValueArray<T, mixed>
     */
    protected function array(string|Closure|null $attribute = null): ValueArray
    {
        /** @var ValueArray<T, mixed> */
        return new ValueArray($attribute);
    }

    /**
     * @param null|string|Closure(T):mixed $attribute
     *
     * @return ValueMixed<T>
     */
    protected function mixed(string|Closure|null $attribute = null): ValueMixed
    {
        /** @var ValueMixed<T> */
        return new ValueMixed($attribute);
    }

    /**
     * @param null|string|Closure(T):mixed $attribute
     *
     * @return ValueEnum<T, UnitEnum>
     */
    protected function enum(string|Closure|null $attribute = null): ValueEnum
    {
        /** @var ValueEnum<T, UnitEnum> */
        return new ValueEnum($attribute);
    }

    /**
     * @param Closure(T):iterable<array-key, mixed> $attribute Members by name, or descriptors named by their attribute
     *
     * @return ValueStruct<T>
     */
    protected function struct(Closure $attribute): ValueStruct
    {
        /** @var ValueStruct<T> */
        return new ValueStruct($attribute);
    }

    /**
     * @param Value<*> $type
     * @param null|string|Closure(T):(array<mixed>|null) $attribute
     * @return ValueArray<T, mixed>
     */
    protected function arrayOf(Value $type, string|Closure|null $attribute = null): ValueArray
    {
        /** @var ValueArray<T, mixed> */
        return (new ValueArray($attribute))->of($type);
    }
}
