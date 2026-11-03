<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Configuration;

/**
 * When a relationship that is not included renders its resource linkage (`data`): `relationship.linkage`, or
 * `whenIncluded()` / `whenLoaded()` on one relationship. An included relationship always renders it.
 *
 * Its `links` and `meta` are rendered in every case.
 */
enum Linkage: string
{
    /**
     * Always: the relation is loaded in batch for the whole collection when it is not loaded yet (the default, 1.x
     * behaviour).
     */
    case Always = 'always';

    /**
     * Only when the relation is already loaded on the model: nothing is loaded for it.
     */
    case Loaded = 'loaded';

    /**
     * Never: the relation is neither loaded nor read unless included.
     */
    case Included = 'included';
}
