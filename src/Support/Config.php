<?php

namespace Ark4ne\JsonApi\Support;

use DateTimeInterface;

class Config
{
    public static bool $nullable = true;

    public static string $date = DateTimeInterface::ATOM;

    public static bool $autoWhenIncluded = false;

    /** @var array<string, bool>|bool  */
    public static array|bool $autoWhenHas = false;

    public static int|null $precision = null;

    public static function boot(): void
    {
        self::$nullable = config('jsonapi.describer.nullable', self::$nullable);
        self::$date = config('jsonapi.describer.date', self::$date);
        self::$precision = config('jsonapi.describer.precision', self::$precision);
        self::$autoWhenHas = config('jsonapi.describer.when-has', self::$autoWhenHas);
        self::$autoWhenIncluded = config('jsonapi.relationship.when-included', self::$autoWhenIncluded);

        if (is_array(self::$autoWhenHas)) {
            self::$autoWhenHas = array_fill_keys(self::$autoWhenHas, true);
        }
    }

    public static function autoWhenHas(string $type): bool
    {
        if (self::$autoWhenHas === false) {
            return false;
        }
        if (self::$autoWhenHas === true) {
            return true;
        }

        return self::$autoWhenHas[$type] ?? false;
    }
}
