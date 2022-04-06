<?php

namespace Ark4ne\JsonApi\Resource\Support;

use Illuminate\Support\Arr;

class With
{
    public static function merge(iterable $base, iterable $with): array
    {
        $base = collect($base)->toArray();

        foreach (collect($with)->toArray() as $key => $value) {
            $base[$key] = array_merge_recursive(
                $base[$key] ?? [],
                $value
            );
        }

        return self::uniqueRecursive($base);
    }

    public static function wash(iterable $with): array
    {
        return collect(self::uniqueRecursive($with))
            ->filter()
            ->toArray();
    }

    private static function uniqueRecursive($value)
    {
        return array_map(static function ($value) {
            if (is_iterable($value)) {
                $value = collect($value)->all();
                $isAssoc = Arr::isAssoc($value);
                $value = array_unique($value, SORT_REGULAR);
                return self::uniqueRecursive($isAssoc ? $value : array_values($value));
            }

            return $value;
        }, collect($value)->all());
    }
}
