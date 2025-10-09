<?php

namespace Ark4ne\JsonApi\Descriptors\Relations;

use Ark4ne\JsonApi\Resources\Relationship;
use Closure;

/**
 * @internal
 */
final class RelationRaw extends Relation
{
    private function __construct(string $related, Closure|string|null $relation)
    {
        parent::__construct($related, $relation);
    }

    protected function value(Closure $value): Relationship
    {
        return ($this->relation)();
    }

    public static function fromRelationship(Relationship $relationship): self
    {
        return new self($relationship->getResource(), static fn() => $relationship);
    }
}
