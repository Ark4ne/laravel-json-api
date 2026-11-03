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
 * Renders the `meta` of the resource object (`toResourceMeta()`).
 */
final readonly class MetaStage implements Stage
{
    public function render(
        ResourceObject $object,
        RenderableResource $resource,
        JsonApiContext $context,
        IncludedRegistry $included,
    ): ResourceObject {
        return $object->withMeta((new ValueResolver())->resolve(
            $resource->renderResourceMeta($context),
            $resource->renderedModel(),
            $context,
        ));
    }
}
