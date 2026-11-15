<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Rendering;

use ArrayIterator;
use IteratorAggregate;
use Traversable;

/**
 * The members of a hook already rendered by `ark4ne/laravel-api-revisions` (`ResourcePlan`): chosen by the sparse
 * fieldset, their conditions decided and their values serialized. The `ValueResolver` gives them as they are.
 *
 * @implements IteratorAggregate<string, mixed>
 */
final readonly class ProjectedValues implements IteratorAggregate
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(public array $values) {}

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->values);
    }
}
