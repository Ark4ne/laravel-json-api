<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Configuration;

/**
 * How `fields[type]` treats the relationships of a resource (`fields.relationships`).
 */
enum SparseRelationships: string
{
    /**
     * `fields[type]` restricts attributes and relationships alike: a relationship is rendered only when named
     * (JSON:API 1.1, the default).
     */
    case Filter = 'filter';

    /**
     * `fields[type]` restricts attributes only: every relationship is rendered (1.x behaviour). A deliberate departure
     * from the specification; a relationship named in `fields` is accepted and ignored.
     */
    case Keep = 'keep';
}
