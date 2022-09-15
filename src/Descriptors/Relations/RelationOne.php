<?php

namespace Ark4ne\JsonApi\Descriptors\Relations;

use Ark4ne\JsonApi\Resources\Relationship;
use Closure;

/**
 * @template T as \Illuminate\Database\Eloquent\Model
 * @extends Relation<T>
 */
class RelationOne extends Relation
{
    protected function value(Closure $value): Relationship
    {
        return new Relationship($this->related, $value, $this->links, $this->meta);
    }
}
