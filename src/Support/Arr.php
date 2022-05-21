<?php

namespace Ark4ne\JsonApi\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr as SupportArr;
use Illuminate\Support\Collection;

class Arr
{
    /**
     * @template TKey as array-key
     * @template TValue
     *
     * @param iterable<TKey, TValue> $iterator
     *
     * @return array<TKey, TValue>
     */
    public static function toArray(iterable $iterator): array
    {
        return (new Collection($iterator))->map(function ($value) {
            if ($value instanceof Arrayable) {
                $value = $value->toArray();
            }

            return is_array($value) ? self::toArray($value) : $value;
        })->all();
    }

    /**
     * @template TKey as array-key
     * @template UKey as array-key
     * @template TValue
     * @template UValue
     *
     * @param iterable<TKey, TValue> $base
     * @param iterable<UKey, UValue> $with
     *
     * @return array<TKey | UKey, TValue | UValue>
     */
    public static function merge(iterable $base, iterable $with): array
    {
        $base = self::toArray($base);

        foreach (self::toArray($with) as $key => $value) {
            $base[$key] = array_merge_recursive(
                $base[$key] ?? [],
                $value
            );
        }

        return self::uniqueRecursive($base);
    }

    /**
     * @template TKey as array-key
     * @template TValue
     *
     * @param iterable<TKey, TValue> $with
     *
     * @return array<TKey, TValue>
     */
    public static function wash(iterable $with): array
    {
        return array_filter(self::uniqueRecursive(self::toArray($with)));
    }

    /**
     * @template TKey as array-key
     * @template TValue
     *
     * @param array<TKey, TValue> $value
     *
     * @return array<TKey, TValue>
     */
    private static function uniqueRecursive(array $value): array
    {
        return array_map(
            static fn($value) => is_array($value)
                ? self::uniqueRecursive(self::uniqueKeyPreserved($value))
                : $value,
            $value
        );
    }

    /**
     * @template TKey as array-key
     * @template TValue
     *
     * @param array<TKey, TValue> $value
     *
     * @return array<TKey, TValue>
     */
    private static function uniqueKeyPreserved(array $value): array
    {
        if (SupportArr::isAssoc($value)) {
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
