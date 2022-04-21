<?php

namespace Ark4ne\JsonApi\Resource\Concerns;

use Ark4ne\JsonApi\Resource\Relationship;
use Closure;

trait Relationize
{
    /**
     * @param \Closure      $value
     * @param \Closure|null $links
     * @param \Closure|null $meta
     *
     * @return \Ark4ne\JsonApi\Resource\Relationship<static>
     */
    public static function relationship(
        Closure $value,
        ?Closure $links = null,
        ?Closure $meta = null
    ): Relationship {
        return new Relationship(static::class, $value, $links, $meta);
    }
}
