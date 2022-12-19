<?php

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Descriptors\Resolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MergeValue;
use Illuminate\Http\Resources\PotentiallyMissing;
use Illuminate\Support\Arr;

trait PrepareData
{
    use Resolver;

    /**
     * @template TKey as array-key
     * @template TValue
     *
     * @param \Illuminate\Http\Request $request
     * @param iterable<TKey, TValue>|null $data
     *
     * @return iterable<TKey, TValue>
     */
    protected function prepareData(Request $request, ?iterable $data): iterable
    {
        return $data ? $this->resolveValues($request, $this->mergeValues($data)) : [];
    }

    /**
     * @template TKey as array-key
     * @template TValue
     *
     * @param iterable<TKey, TValue> $data
     *
     * @return array<TKey, TValue>
     */
    protected function mergeValues(iterable $data): array
    {
        $data = collect($data)->all();

        $index = -1;

        foreach ($data as $key => $value) {
            $index++;

            if (is_iterable($value)) {
                $data[$key] = $this->mergeValues($value);

                continue;
            }

            if (is_numeric($key) && $value instanceof MergeValue) {
                $first = array_slice($data, 0, $index, true);
                $second = array_slice($data, $index + 1, null, true);

                $first = $this->mergeIgnoreMissing($first, $this->mergeValues($value->data));

                if (Arr::isList($value->data)) {
                    return array_merge($first, $this->mergeValues(array_values($second)));
                }

                return $this->mergeIgnoreMissing($first, $this->mergeValues($second));
            }
        }

        return $data;
    }

    /**
     * Merge two array without erase concrete value by missing value.
     *
     * @param array $array
     * @param array $merge
     * @return array
     */
    private function mergeIgnoreMissing(array $array, array $merge): array
    {
        foreach ($merge as $key => $item) {
            if (!isset($array[$key]) || $this->isValueMissing($array[$key]) || !$this->isValueMissing($item)) {
                $array[$key] = $item;
            }
        }

        return $array;
    }

    /**
     * Check if a value is missing
     *
     * @param $value
     * @return bool
     */
    protected function isValueMissing($value): bool
    {
        return ($value instanceof PotentiallyMissing && $value->isMissing()) ||
            ($value instanceof self &&
                $value->resource instanceof PotentiallyMissing &&
                $value->isMissing());
    }
}
