<?php

namespace Ark4ne\JsonApi\Resource\Concerns;

use Ark4ne\JsonApi\Resource\Relationship;
use Closure;

trait AsRelationship
{
    public function asRelationship(
        iterable|Closure $links = [],
        iterable|Closure $meta = []
    ): Relationship {
        return new Relationship($this, $links, $meta);
    }
}
