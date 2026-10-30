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
use Ark4ne\JsonApi\Rendering\ValueResolver;

/**
 * Renders `relationships`: resource linkage (with the meta of each identifier), links and meta of each relationship
 * the sparse fieldset of the type allows (`fields.relationships = 'filter'`; all of them with `'keep'`). The related
 * resources of an included relationship go to the registry, rendered in the context of their include path.
 *
 * Links and meta are resolved like the other members (descriptors, closures, conditions): those of the relationship
 * against the resource, the meta of an identifier against its related resource.
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
            $relationship = self::relationshipObject($resolved, $resource->renderedModel(), $context);

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
     * @return array{data?: array{type: string, id: string, meta?: array<array-key, mixed>}|list<array{type: string, id: string, meta?: array<array-key, mixed>}>|null, links?: array<array-key, mixed>, meta?: array<array-key, mixed>}
     */
    private static function relationshipObject(ResolvedRelationship $resolved, mixed $model, JsonApiContext $context): array
    {
        $values = new ValueResolver();
        $relationship = [];

        if ($resolved->hasData) {
            $identifiers = [];
            foreach ($resolved->resources() as $index => $related) {
                $identifier = ResourceObject::identify(
                    $related->renderType($context),
                    $related->renderIdentifier($context),
                )->identifier();

                $meta = isset($resolved->identifierMeta[$index]) && $resolved->identifierMeta[$index] !== []
                    ? $values->resolve($resolved->identifierMeta[$index], $related->renderedModel(), $context)
                    : [];

                if ($meta !== []) {
                    $identifier['meta'] = $meta;
                }

                $identifiers[] = $identifier;
            }

            $relationship['data'] = $resolved->cardinality === Cardinality::Many ? $identifiers : $identifiers[0] ?? null;
        }

        $links = $values->resolve($resolved->links, $model, $context);
        $meta = $values->resolve($resolved->meta, $model, $context);

        if ($links !== []) {
            $relationship['links'] = $links;
        }

        if ($meta !== []) {
            $relationship['meta'] = $meta;
        }

        return $relationship;
    }
}
