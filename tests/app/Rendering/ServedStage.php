<?php

declare(strict_types=1);

namespace Test\app\Rendering;

use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Rendering\IncludedRegistry;
use Ark4ne\JsonApi\Rendering\RenderableResource;
use Ark4ne\JsonApi\Rendering\ResourceObject;
use Ark4ne\JsonApi\Rendering\Stage;

/**
 * A stage of the application, registered through `jsonapi.rendering.stages`.
 */
final class ServedStage implements Stage
{
    public function render(
        ResourceObject $object,
        RenderableResource $resource,
        JsonApiContext $context,
        IncludedRegistry $included,
    ): ResourceObject {
        return $object->withMeta([...$object->meta, 'served' => true]);
    }
}
