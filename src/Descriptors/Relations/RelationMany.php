<?php

namespace Ark4ne\JsonApi\Descriptors\Relations;

use Ark4ne\JsonApi\Resources\Relationship;
use Closure;

/**
 * @template T
 * @extends Relation<T>
 */
class RelationMany extends Relation
{
    protected function value(Closure $value): Relationship
    {
        $relation = new Relationship($this->related, $value, $this->links, $this->meta);
        $relation->asCollection();

        return $relation;
    }
}
