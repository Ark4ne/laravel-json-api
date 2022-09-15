<?php

namespace Ark4ne\JsonApi\Descriptors\Relations;

use Ark4ne\JsonApi\Resources\Relationship;
use Closure;

class RelationOne extends Relation
{
    protected function value(Closure $value): Relationship
    {
        return new Relationship($this->related, $value, $this->links, $this->meta);
    }
}
