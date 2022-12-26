<?php

namespace Ark4ne\JsonApi\Descriptors\Relations;

use Ark4ne\JsonApi\Resources\Relationship;
use Closure;
use Illuminate\Http\Resources\MissingValue;

final class RelationMissing extends Relation
{
    private function __construct(string $related, Closure|string|null $relation)
    {
        parent::__construct($related, $relation);

        $this->when(false);
    }

    protected function value(Closure $value): Relationship
    {
        /** @var Relationship $relation */
        $relation = ($this->relation)();
        $relation->withValue(fn() => new MissingValue);
        return $relation;
    }

    public static function fromRelationship(Relationship $relationship): self
    {
        return new self($relationship->getResource(), fn() => $relationship);
    }
}
