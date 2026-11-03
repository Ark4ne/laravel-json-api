<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Rendering;

use Ark4ne\JsonApi\Context\JsonApiContext;

/**
 * A relationship of one resource, as the renderer reads it. `Resources\Relationship` implements it.
 */
interface RelationshipSource
{
    /**
     * Resolves the related resources, links and meta.
     *
     * @param bool $included Whether the relationship is included: otherwise, its linkage decides whether its data is
     *                       resolved (`Configuration\Linkage`)
     */
    public function resolve(JsonApiContext $context, bool $included): ResolvedRelationship;
}
