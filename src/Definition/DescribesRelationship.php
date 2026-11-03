<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Definition;

use Closure;

/**
 * What `toRelationships()` may return for a relationship: a relation descriptor (`$this->one()`) or a relationship
 * (`PostResource::relationship()`).
 */
interface DescribesRelationship
{
    public function definition(string $name): RelationshipDefinition;

    /**
     * The constraint closures of the eager-loads, by relation path (`withLoad(['comments' => fn($query) => …])`).
     *
     * @return array<string, Closure>
     */
    public function loadConstraints(): array;
}
