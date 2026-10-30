<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Descriptors\Describer;
use Ark4ne\JsonApi\Descriptors\Relations\Relation;
use Ark4ne\JsonApi\Rendering\RelationshipSource;
use Ark4ne\JsonApi\Resources\Relationship;
use Ark4ne\JsonApi\Support\Values;
use Illuminate\Http\Resources\PotentiallyMissing;

/**
 * Implements `Rendering\RenderableResource` for `JsonApiResource`: bridges from the renderer to the user hooks, which
 * stay `protected` (docs/architecture.md §2 and §8).
 *
 * The context of the rendering is kept for the helpers the hooks call (`whenInFields()`, `whenIncluded()`).
 */
trait Renderable
{
    /**
     * @internal Read by `whenInFields()` and `whenIncluded()`.
     */
    protected ?JsonApiContext $renderingContext = null;

    /**
     * @internal
     */
    public function renderType(JsonApiContext $context): string
    {
        $this->renderingContext = $context;

        return $this->toType($context->request);
    }

    /**
     * @internal
     */
    public function renderIdentifier(JsonApiContext $context): int|string
    {
        $this->renderingContext = $context;

        return $this->toIdentifier($context->request);
    }

    /**
     * @internal
     */
    public function renderedModel(): mixed
    {
        return $this->resource;
    }

    /**
     * @internal
     *
     * @return iterable<array-key, mixed>
     */
    public function renderAttributes(JsonApiContext $context): iterable
    {
        $this->renderingContext = $context;

        return $this->autoWhenHas(
            $this->toAttributes($context->request),
            $context->configuration->autoWhenHas('attributes'),
        );
    }

    /**
     * @internal
     *
     * @return iterable<string, RelationshipSource>
     */
    public function renderRelationships(JsonApiContext $context): iterable
    {
        $this->renderingContext = $context;

        $relationships = $this->autoWhenIncluded(
            Values::mergeValues($this->toRelationships($context->request)),
            $context->configuration->whenIncluded,
        );

        foreach ($relationships as $key => $relationship) {
            if ($relationship instanceof PotentiallyMissing && $relationship->isMissing()) {
                continue;
            }

            $name = (string) Describer::retrieveName($relationship, $key);

            if ($relationship instanceof Relation) {
                yield $name => $relationship->resolveFor($context->request, $this->resource, $name);
            } elseif ($relationship instanceof Relationship) {
                yield $name => $relationship;
            }
        }
    }

    /**
     * @internal
     *
     * @return iterable<array-key, mixed>
     */
    public function renderLinks(JsonApiContext $context): iterable
    {
        $this->renderingContext = $context;

        return $this->toLinks($context->request) ?? [];
    }

    /**
     * @internal
     *
     * @return iterable<array-key, mixed>
     */
    public function renderResourceMeta(JsonApiContext $context): iterable
    {
        $this->renderingContext = $context;

        return $this->autoWhenHas(
            $this->toResourceMeta($context->request) ?? [],
            $context->configuration->autoWhenHas('resource-meta'),
        );
    }

    /**
     * @internal
     *
     * @return iterable<array-key, mixed>
     */
    public function renderDocumentMeta(JsonApiContext $context): iterable
    {
        $this->renderingContext = $context;

        return $this->autoWhenHas(
            $this->toMeta($context->request) ?? [],
            $context->configuration->autoWhenHas('meta'),
        );
    }
}
