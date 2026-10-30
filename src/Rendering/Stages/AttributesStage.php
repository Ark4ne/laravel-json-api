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
 * Renders `attributes`: the members of `toAttributes()` restricted by the sparse fieldset of the type, resolved
 * lazily (`ValueResolver`).
 */
final readonly class AttributesStage implements Stage
{
    public function render(
        ResourceObject $object,
        RenderableResource $resource,
        JsonApiContext $context,
        IncludedRegistry $included,
    ): ResourceObject {
        return $object->withAttributes((new ValueResolver())->resolve(
            $resource->renderAttributes($context),
            $resource->renderedModel(),
            $context,
            $object->type,
        ));
    }
}
