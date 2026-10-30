<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Rendering\Stages;

use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Rendering\IncludedRegistry;
use Ark4ne\JsonApi\Rendering\RenderableResource;
use Ark4ne\JsonApi\Rendering\ResourceObject;
use Ark4ne\JsonApi\Rendering\Stage;
use Ark4ne\JsonApi\Rendering\ValueResolver;

/**
 * Renders the `links` of the resource object (`toLinks()`).
 */
final readonly class LinksStage implements Stage
{
    public function render(
        ResourceObject $object,
        RenderableResource $resource,
        JsonApiContext $context,
        IncludedRegistry $included,
    ): ResourceObject {
        return $object->withLinks((new ValueResolver())->resolve(
            $resource->renderLinks($context),
            $resource->renderedModel(),
            $context,
        ));
    }
}
