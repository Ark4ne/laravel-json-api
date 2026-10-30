<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Descriptors\Relations\Relation;
use Ark4ne\JsonApi\Descriptors\Values\Value;
use Ark4ne\JsonApi\Resources\Relationship;
use Ark4ne\JsonApi\Support\Values;
use Illuminate\Support\Collection;

trait PrepareData
{
    protected ?bool $autoWhenHas = null;

    protected ?bool $autoWhenIncluded = null;

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
     * Applies `whenHas()` to the descriptors, as the resource says (`$autoWhenHas`), otherwise as the configuration of
     * the scope says. A descriptor that sets it itself keeps its choice.
     *
     * @template TKey as array-key
     * @template TValue
     * @param iterable<TKey, TValue> $data
     *
     * @return iterable<TKey, TValue>
     */
    protected function autoWhenHas(iterable $data, bool $byConfiguration): iterable
    {
        if (!($this->autoWhenHas ?? $byConfiguration)) {
            return $data;
        }

        // Descriptors are immutable: the modified ones replace the originals.
        return (new Collection($data))
            ->map(static fn($value) => $value instanceof Value && $value->appliesAutoWhenHas(true)
                ? $value->autoWhenHas()
                : $value)
            ->all();
    }

    /**
     * Resolves the relationships only when included, as the resource says (`$autoWhenIncluded`), otherwise as the
     * configuration says.
     *
     * @template TKey as array-key
     * @template TValue
     * @param iterable<TKey, TValue> $data
     *
     * @return iterable<TKey, TValue>
     */
    protected function autoWhenIncluded(iterable $data, bool $byConfiguration): iterable
    {
        if (!($this->autoWhenIncluded ?? $byConfiguration)) {
            return $data;
        }

        return (new Collection($data))
            ->map(static fn($relation) => $relation instanceof Relation || $relation instanceof Relationship
                ? $relation->whenIncluded()
                : $relation)
            ->all();
    }

}
