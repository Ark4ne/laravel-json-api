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
     * @param array<mixed> $array
     * @param string $prepend
     * @return array<mixed>
     */
    public static function flatDot(array $array, string $prepend = ''): array
    {
        return self::flattenDot($array, $prepend, uniqid('self-', false));
    }

    /**
     * @param array<mixed> $array
     * @param string $prepend
     * @param string $saveKey
     * @return array<mixed>
     */
    private static function flattenDot(array $array, string $prepend, string $saveKey): array
    {
        $array = self::undot($array, $saveKey);

        $results = [];

        foreach ($array as $key => $value) {
            if (is_array($value) && !empty($value)) {
                foreach (self::flattenDot($value, $prepend ? "$prepend.$key" : $key, $saveKey) as $itemKey => $item) {
                    $results[str_replace(".$saveKey", '', $itemKey)] = $item;
                }
            } elseif ($key === $saveKey) {
                $results[$prepend] = $value;
            } else {
                $results[$prepend ? "$prepend.$key" : $key] = $value;
            }
        }

        return $results;
    }

    /**
     * @param array<mixed> $array
     * @param string|null $saveKey
     * @return array<mixed>
     */
    public static function undot(array $array, null|string $saveKey = null): array
    {
        $results = [];

        ksort($array);

        foreach ($array as $key => $value) {
            $value = is_array($value) ? self::undot($value, $saveKey) : $value;

            self::apply($results, $key, $value, $saveKey);
        }

        return $results;
    }

    /**
     * @param array<mixed> $array
     * @param string $path
     * @param mixed $value
     * @param string|null $saveKey
     * @return mixed
     */
    public static function apply(array &$array, string $path, mixed $value, null|string $saveKey = null): mixed
    {
        $keys = explode('.', $path);

        $keysCount = count($keys);

        foreach ($keys as $i => $key) {
            if ($keysCount === 1) {
                break;
            }

            $keysCount--;
            unset($keys[$i]);

            // If the key doesn't exist at this depth, we will just create an empty array
            // to hold the next value, allowing us to create the arrays to hold final
            // values at the correct depth. Then we'll keep digging into the array.
            if (!isset($array[$key])) {
                $array[$key] = [];
            }
            if (!is_array($array[$key])) {
                $array[$key] = $saveKey
                    ? [$saveKey => $array[$key]]
                    : [];
            }

            $array = &$array[$key];
        }

        $array[array_shift($keys)] = $value;

        return $array;
    }

    /**
     * @template TKey as array-key
     * @template TValue
     *
     * @param array<TKey, TValue> $array
     * @param array<array-key, mixed> $struct
     *
     * @return array<TKey, TValue>
     */
    public static function intersectKeyStruct(array $array, array $struct): array
    {
        $res = array_intersect_key($array, $struct);

        foreach ($res as $key => $value) {
            if (is_array($value) && is_array($struct[$key])) {
                $res[$key] = self::intersectKeyStruct($value, $struct[$key]);
            }
        }

        return $res;
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
