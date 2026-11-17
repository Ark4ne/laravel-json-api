<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Configuration\Linkage;
use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Descriptors\Relations\RelationRaw;
use Ark4ne\JsonApi\Exceptions\RenderingException;
use Ark4ne\JsonApi\Loading\LoadedRelations;
use Ark4ne\JsonApi\Rendering\RelationshipSource;
use Ark4ne\JsonApi\Resources\Relationship;

/**
 * Implements `Rendering\RenderableResource` for `JsonApiResource`: bridges from the renderer to the user hooks, which
 * stay `protected` (docs/architecture.md §2 and §8).
 *
 * The context of the rendering is kept for the helpers the hooks call (`whenInFields()`, `whenInIncludes()`).
 */
trait Renderable
{
    /**
     * @internal Read by `whenInFields()` and `whenInIncludes()`.
     */
    protected ?JsonApiContext $renderingContext = null;

    /**
     * @internal Identifies the resource first, as the primary data or reached through a relationship: a resource that
     *           does not exist at the revision of the rendering is never written.
     *
     * @throws RenderingException when the resource does not exist at the revision of the rendering
     */
    public function renderType(JsonApiContext $context): string
    {
        $this->renderingContext = $context;

        if (!Definitions::exists(static::class, $context->revision)) {
            throw RenderingException::notInRevision(static::class, $context->revision);
        }

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

        // The default attributes, those of the model: no shape names them, they are rendered as they are.
        if (static::hasDefaultAttributes()) {
            return $this->toAttributes($context->request);
        }

        return $this->valuesPlan($context)->attributes($this->resource, $context);
    }

    /**
     * @internal
     *
     * @return iterable<string, RelationshipSource>
     */
    public function renderRelationships(JsonApiContext $context): iterable
    {
        $this->renderingContext = $context;

        $definitions = Definitions::for(static::class, $context->revision)->relationships;
        $model = $this->resource;

        // The plan gives their names at the revision and the relation in force, declared once per response.
        foreach ($this->valuesPlan($context)->relationships($model, $context) as $name => [$key, $relation]) {
            $definition = $definitions[$name] ?? null;
            if ($relation instanceof RelationRaw) {
                // Its own conditions (`can()`, `whenLoaded()`…): those of a relation are read with its value.
                if (!$relation->passes($context->request, $model, $key)) {
                    continue;
                }
                $relationship = $relation->relationship();
                if (!$relationship instanceof Relationship) {
                    continue;
                }
            } else {
                $relationship = Relationship::declared($relation->resolveFor($context->request, $model, $key, $context->configuration));
            }

            // Only `Linkage::Loaded` and the filters read whether the relation is loaded.
            yield $name => $definition === null || (
                ($definition->linkage ?? $context->configuration->linkage) !== Linkage::Loaded
                && !$relationship->filtered()
            )
                ? $relationship
                : $relationship->loadedWhen(static fn() => LoadedRelations::has($model, $definition));
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

        return $this->valuesPlan($context)->meta($this->resource, $context);
    }

    /**
     * @internal
     *
     * @return iterable<array-key, mixed>
     */
    public function renderDocumentMeta(JsonApiContext $context): iterable
    {
        $this->renderingContext = $context;

        return $this->valuesPlan($context)->documentMeta($this->resource, $context);
    }
}
