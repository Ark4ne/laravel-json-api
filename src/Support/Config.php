<?php

namespace Ark4ne\JsonApi\Support;

use DateTimeInterface;

class Config
{
    public static bool $nullable = true;

    public static string $date = DateTimeInterface::ATOM;

    public static bool $autoWhenIncluded = false;

    public static bool $autoWhenHas = false;

    public static int|null $precision = null;

    public static function boot(): void
    {
        self::$nullable = config('jsonapi.describer.nullable', self::$nullable);
        self::$date = config('jsonapi.describer.date', self::$date);
        self::$precision = config('jsonapi.describer.precision', self::$precision);
        self::$autoWhenHas = config('jsonapi.attribute.when-has', self::$autoWhenHas);
        self::$autoWhenIncluded = config('jsonapi.relationship.when-included', self::$autoWhenIncluded);
    }
}
