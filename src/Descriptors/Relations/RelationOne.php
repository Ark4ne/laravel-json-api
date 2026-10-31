<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Relations;

use Ark4ne\JsonApi\Resources\Relationship;
use Closure;
use LogicException;

/**
 * @template T
 * @extends Relation<T>
 */
class RelationOne extends Relation
{
    protected function value(Closure $value): Relationship
    {
        return new Relationship($this->related ?? throw new LogicException('The related resource is resolved by resolveRelated() first.'), $value, $this->links, $this->meta, $this->identifierMeta);
    }
}
