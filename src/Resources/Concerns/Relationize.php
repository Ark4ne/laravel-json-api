<?php

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Resources\Relationship;
use Closure;

trait Relationize
{
    /**
     * @param Closure      $value
     * @param Closure|null $links
     * @param Closure|null $meta
     *
     * @return \Ark4ne\JsonApi\Resources\Relationship<static>
     */
    public static function relationship(
        Closure $value,
        ?Closure $links = null,
        ?Closure $meta = null
    ): Relationship {
        return new Relationship(static::class, $value, $links, $meta);
    }
}
