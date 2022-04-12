<?php

namespace Ark4ne\JsonApi\Resource\Concerns;

use Ark4ne\JsonApi\Resource\Relationship;
use Closure;

trait Relationize
{
    /**
     * Transform JSON:API resource as relationship
     *
     * @param iterable|\Closure $links
     * @param iterable|\Closure $meta
     *
     * @return \Ark4ne\JsonApi\Resource\Relationship
     */
    public function asRelationship(
        iterable|Closure $links = [],
        iterable|Closure $meta = []
    ): Relationship {
        return new Relationship($this, $links, $meta);
    }
}
