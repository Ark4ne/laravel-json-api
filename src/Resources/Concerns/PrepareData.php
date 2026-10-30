<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Configuration\Linkage;
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
     * Renders the linkage of every relationship only when included (`whenIncluded()`) when the resource says so
     * (`$autoWhenIncluded = true`), always when it says not to (`false`, for the relationships that do not set theirs);
     * `relationship.linkage` applies otherwise.
     *
     * @template TKey as array-key
     * @template TValue
     * @param iterable<TKey, TValue> $data
     *
     * @return iterable<TKey, TValue>
     */
    protected function autoWhenIncluded(iterable $data): iterable
    {
        if ($this->autoWhenIncluded === null) {
            return $data;
        }

        $whenIncluded = $this->autoWhenIncluded;

        return (new Collection($data))
            ->map(static fn($relation) => match (true) {
                !$relation instanceof Relation && !$relation instanceof Relationship => $relation,
                $whenIncluded => $relation->whenIncluded(),
                default => $relation->defaultLinkage(Linkage::Always),
            })
            ->all();
    }
}
