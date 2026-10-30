<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Rendering\Stages;

use Ark4ne\JsonApi\Configuration\SparseRelationships;
use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Definition\Cardinality;
use Ark4ne\JsonApi\Rendering\IncludedRegistry;
use Ark4ne\JsonApi\Rendering\RenderableResource;
use Ark4ne\JsonApi\Rendering\ResolvedRelationship;
use Ark4ne\JsonApi\Rendering\ResourceObject;
use Ark4ne\JsonApi\Rendering\Stage;

/**
 * Renders `relationships`: resource linkage, links and meta of each relationship the sparse fieldset of the type
 * allows (`fields.relationships = 'filter'`; all of them with `'keep'`). The related resources of an included
 * relationship go to the registry, rendered in the context of their include path.
 *
 * A relationship object always has `data`, `links` or `meta`; otherwise it is left out. An empty to-one renders
 * `"data": null`, an empty to-many `"data": []`.
 */
final readonly class RelationshipsStage implements Stage
{
    public function render(
        ResourceObject $object,
        RenderableResource $resource,
        JsonApiContext $context,
        IncludedRegistry $included,
    ): ResourceObject {
        $filter = $context->configuration->sparseRelationships === SparseRelationships::Filter;
        $relationships = [];

        foreach ($resource->renderRelationships($context) as $name => $source) {
            if ($filter && !$context->fields->allows($object->type, $name)) {
                continue;
            }

            $isIncluded = $context->includes($name);
            $resolved = $source->resolve($context, $isIncluded);
            $relationship = self::relationshipObject($resolved, $context);

            if ($relationship === []) {
                continue;
            }

            $relationships[$name] = $relationship;

            if ($isIncluded) {
                $path = $context->withPath($name);

                foreach ($resolved->resources() as $related) {
                    $included->include($related, $path);
                }
            }
        }

        return $object->withRelationships($relationships);
    }

    /**
     * @return array{data?: array{type: string, id: string}|list<array{type: string, id: string}>|null, links?: array<array-key, mixed>, meta?: array<array-key, mixed>}
     */
    private static function relationshipObject(ResolvedRelationship $resolved, JsonApiContext $context): array
    {
        $relationship = [];

        if ($resolved->hasData) {
            $identifiers = array_map(
                static fn(RenderableResource $related) => ResourceObject::identify(
                    $related->renderType($context),
                    $related->renderIdentifier($context),
                )->identifier(),
                $resolved->resources(),
            );

            $relationship['data'] = $resolved->cardinality === Cardinality::Many ? $identifiers : $identifiers[0] ?? null;
        }

        if ($resolved->links !== []) {
            $relationship['links'] = $resolved->links;
        }

        if ($resolved->meta !== []) {
            $relationship['meta'] = $resolved->meta;
        }

        return $relationship;
    }
}
