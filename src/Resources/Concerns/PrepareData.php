<?php

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Descriptors\Resolver;
use Ark4ne\JsonApi\Descriptors\Values\Value;
use Ark4ne\JsonApi\Support\Config;
use Ark4ne\JsonApi\Support\Values;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

trait PrepareData
{
    use Resolver;

    /**
     * @template TKey as array-key
     * @template TValue

     * @param iterable<TKey, TValue> $data
     *
     * @return iterable<TKey, TValue>
     */
    protected function mergeValues(iterable $data) : iterable {

        return Values::mergeValues($data);
    }

    /**
     * @template TKey as array-key
     * @template TValue

     * @param iterable<TKey, TValue> $data
     *
     * @return iterable<TKey, TValue>
     */
    protected function autoWhenHas(iterable $data): iterable
    {
        if (!Config::$autoWhenHas) {
            return $data;
        }

        return (new Collection($data))
            ->map(fn($value, int|string $key) => $value instanceof Value
                ? $value->whenHas()
                : $this->whenHas($key, $value));
    }

    /**
     * @deprecated
     *
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
        if ($data === null) {
            return [];
        }

        return $data ? $this->resolveValues($request, Values::mergeValues($data)) : [];
    }
}
