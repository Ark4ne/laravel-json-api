<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Support;

/**
 * The entry of a polymorphic map for a model: that of the first class it is an instance of. Shared by the loading and
 * the rendering, so that both pick the same resource.
 */
final class Morphs
{
    /**
     * @template T
     *
     * @param array<class-string, T> $map
     *
     * @return T|null
     */
    public static function of(array $map, mixed $model): mixed
    {
        foreach ($map as $class => $entry) {
            if ($model instanceof $class) {
                return $entry;
            }
        }

        return null;
    }
}
