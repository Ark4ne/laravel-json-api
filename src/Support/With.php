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

    private static function uniqueRecursive($value): array
    {
        return array_map(static function ($value) {
            if (is_iterable($value)) {
                return self::uniqueRecursive(self::uniqueKeyPreserved(collect($value)->all()));
            }

            return $value;
        }, collect($value)->all());
    }

    private static function uniqueKeyPreserved(array $value): array
    {
        if (Arr::isAssoc($value)) {
            $entries = array_map(static fn($k, $v) => [$k, $v], array_keys($value), array_values($value));
            $entries = array_values(array_unique($entries, SORT_REGULAR));
            return array_combine(
                array_column($entries, 0),
                array_column($entries, 1)
            );
        }

        return array_values(array_unique($value, SORT_REGULAR));
    }
}
