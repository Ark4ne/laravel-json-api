<?php

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Descriptors\Resolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
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
     * @template TKey1 as array-key
     * @template TValue1
     * @template TKey2 as array-key
     * @template TValue2
     *
     * @param array<TKey1, TValue1> $array
     * @param array<TKey2, TValue2> $merge
     * @return array<TKey1&TKey2, TValue1&TValue2>
     */
    private function mergeIgnoreMissing(array $array, array $merge): array
    {
        foreach ($merge as $key => $item) {
            if (!isset($array[$key]) || $this->isMissing($array[$key]) || !$this->isMissing($item)) {
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
    private function isMissing(mixed $resource): bool
    {
        return ($resource instanceof PotentiallyMissing && $resource->isMissing())
            || ($resource instanceof JsonResource &&
                $resource->resource instanceof PotentiallyMissing &&
                $resource->resource->isMissing());
    }
}
