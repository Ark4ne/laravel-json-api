<?php

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Descriptors\Resolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MergeValue;
use Illuminate\Support\Arr;

trait PrepareData
{
    use Resolver;

    /**
     * @template TKey extends array-key
     * @template TValue
     *
     * @param iterable<TKey, TValue> $data
     *
     * @return iterable<TKey, TValue>
     */
    protected function prepareData(Request $request, ?iterable $data): iterable
    {
        return $data ? $this->resolveValues($request, $this->mergeValues($data)) : [];
    }

    /**
     * @template TKey extends array-key
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
                if (Arr::isList($value->data)) {
                    return array_merge(
                        array_merge(array_slice($data, 0, $index, true), $this->mergeValues($value->data)),
                        $this->mergeValues(array_values(array_slice($data, $index + 1, null, true)))
                    );
                }

                return array_slice($data, 0, $index, true) +
                    $this->mergeValues($value->data) +
                    $this->mergeValues(array_slice($data, $index + 1, null, true));
            }
        }

        return $data;
    }
}
