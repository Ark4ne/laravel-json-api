<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Relations;

use Ark4ne\JsonApi\Definition\Cardinality;
use Ark4ne\JsonApi\Resources\Relationship;
use Closure;
use LogicException;

/**
 * @template T
 * @extends Relation<T>
 */
class RelationMany extends Relation
{
    protected function value(Closure $value): Relationship
    {
        $relation = new Relationship($this->related ?? throw new LogicException('The related resource is resolved by resolveRelated() first.'), $value, $this->links, $this->meta, $this->identifierMeta);
        $relation->asCollection();

        return $relation;
    }

    protected function cardinality(): Cardinality
    {
        return Cardinality::Many;
    }
}
