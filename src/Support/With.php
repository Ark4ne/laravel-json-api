<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * @deprecated Will be replaced by Arr::class.
 * @see \Ark4ne\JsonApi\Support\Arr
 */
class With
{
    /**
     * @template TKey as array-key
     * @template TValue
     * @template UKey as array-key
     * @template UValue
     *
     * @param iterable<TKey, TValue> $base
     * @param iterable<UKey, UValue> $with
     *
     * @return array<TKey & UKey, TValue & UValue>
     */
    public static function merge(iterable $base, iterable $with): array
    {
        $base = (new Collection($base))->toArray();

        foreach ((new Collection($with))->toArray() as $key => $value) {
            $base[$key] = array_merge_recursive(
                $base[$key] ?? [],
                $value,
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
        return (new Collection(self::uniqueRecursive($with)))
            ->filter()
            ->toArray();
    }

    /**
     * @template TKey as array-key
     * @template TValue
     *
     * @param iterable<TKey, TValue> $value
     *
     * @return array<TKey, TValue>
     */
    private static function uniqueRecursive(iterable $value): array
    {
        return array_map(static function ($value) {
            if (is_iterable($value)) {
                return self::uniqueRecursive(self::uniqueKeyPreserved((new Collection($value))->all()));
            }

            return $value;
        }, (new Collection($value))->all());
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
        if (Arr::isAssoc($value)) {
            $entries = array_map(static fn($k, $v) => [$k, $v], array_keys($value), array_values($value));
            $entries = array_values(array_unique($entries, SORT_REGULAR));
            return array_combine(
                array_column($entries, 0),
                array_column($entries, 1),
            );
        }

        return array_values(array_unique($value, SORT_REGULAR));
    }
}
