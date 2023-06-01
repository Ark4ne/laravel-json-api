<?php

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Descriptors\Relations\Relation;
use Ark4ne\JsonApi\Descriptors\Resolver;
use Ark4ne\JsonApi\Descriptors\Values\Value;
use Ark4ne\JsonApi\Resources\Relationship;
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
    protected function mergeValues(iterable $data): iterable
    {

        return Values::mergeValues($data);
    }

    /**
     * @template TKey as array-key
     * @template TValue
     * @param iterable<TKey, TValue> $data
     *
     * @return iterable<TKey, TValue>
     */
    protected function autoWhenHas(iterable $data, string $for): iterable
    {
        $autoWhenHas = $this->autoWhenHas ?? null;

        if ($autoWhenHas || (Config::autoWhenHas($for) && $autoWhenHas !== false)) {
            (new Collection($data))
                ->each(fn($value, int|string $key) => $value instanceof Value
                    ? $value->autoWhenHas()
                    : $value);
        }

        return $data;
    }

    /**
     * @template TKey as array-key
     * @template TValue
     * @param iterable<TKey, TValue> $data
     *
     * @return iterable<TKey, TValue>
     */
    protected function autoWhenIncluded(iterable $data): iterable
    {
        $autoWhenIncluded = $this->autoWhenIncluded ?? null;

        if ($autoWhenIncluded || (Config::$autoWhenIncluded && $autoWhenIncluded !== false)) {
            (new Collection($data))
                ->each(fn($relation, int|string $key) => $relation instanceof Relation || $relation instanceof Relationship
                    ? $relation->whenIncluded()
                    : $relation);
        }

        return $data;
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param iterable<TKey, TValue>|null $data
     *
     * @return iterable<TKey, TValue>
     * @deprecated
     *
     * @template TKey as array-key
     * @template TValue
     *
     */
    protected function prepareData(Request $request, ?iterable $data): iterable
    {
        if ($data === null) {
            return [];
        }

        return $data ? $this->resolveValues($request, Values::mergeValues($data)) : [];
    }
}
