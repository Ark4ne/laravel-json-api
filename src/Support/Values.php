<?php

namespace Ark4ne\JsonApi\Support;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MergeValue;
use Illuminate\Http\Resources\PotentiallyMissing;
use Illuminate\Support\Arr;

class Values
{

    /**
     * @template TKey as array-key
     * @template TValue
     *
     * @param iterable<TKey, TValue> $data
     *
     * @return array<TKey, TValue>
     */
    public static function mergeValues(iterable $data): array
    {
        $data = collect($data)->all();

        $index = -1;

        foreach ($data as $key => $value) {
            $index++;

            if (is_iterable($value)) {
                $data[$key] = self::mergeValues($value);

                continue;
            }

            if (is_numeric($key) && $value instanceof MergeValue) {
                $first = array_slice($data, 0, $index, true);
                $second = array_slice($data, $index + 1, null, true);

                $first = self::mergeIgnoreMissing($first, self::mergeValues($value->data));

                if (Arr::isList($value->data)) {
                    return array_merge($first, self::mergeValues(array_values($second)));
                }

                return self::mergeIgnoreMissing($first, self::mergeValues($second));
            }
        }

        return $data;
    }

    /**
     * Merge two array without erase concrete value by missing value.
     *
     * @template TKey1 as array-key
     * @template TValue1
     * @template TKey2 as array-key
     * @template TValue2
     *
     * @param array<TKey1, TValue1> $array
     * @param array<TKey2, TValue2> $merge
     * @return array<TKey1&TKey2, TValue1&TValue2>
     */
    private static function mergeIgnoreMissing(array $array, array $merge): array
    {
        foreach ($merge as $key => $item) {
            if (!isset($array[$key]) || self::isMissing($array[$key]) || !self::isMissing($item)) {
                $array[$key] = $item;
            }
        }

        return $array;
    }

    /**
     * @param mixed|PotentiallyMissing|JsonResource $resource
     *
     * @return bool
     */
    public static function isMissing(mixed $resource): bool
    {
        return ($resource instanceof PotentiallyMissing && $resource->isMissing())
            || ($resource instanceof JsonResource &&
                $resource->resource instanceof PotentiallyMissing &&
                $resource->resource->isMissing());
    }
}
