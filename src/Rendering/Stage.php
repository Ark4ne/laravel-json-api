<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Rendering;

use Ark4ne\JsonApi\Context\JsonApiContext;

/**
 * One step of the rendering of a resource object (pattern Pipeline, docs/architecture.md §8).
 *
 * A stage adds what it renders to the object and returns it; it may add the resources it includes to the registry.
 */
interface Stage
{
    public function render(
        ResourceObject $object,
        RenderableResource $resource,
        JsonApiContext $context,
        IncludedRegistry $included,
    ): ResourceObject;
}
