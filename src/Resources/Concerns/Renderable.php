<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Configuration\Linkage;
use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Descriptors\Describer;
use Ark4ne\JsonApi\Descriptors\Relations\Relation;
use Ark4ne\JsonApi\Descriptors\Relations\RelationRaw;
use Ark4ne\JsonApi\Descriptors\RevisedMembers;
use Ark4ne\JsonApi\Exceptions\RenderingException;
use Ark4ne\JsonApi\Loading\LoadedRelations;
use Ark4ne\JsonApi\Rendering\RelationshipSource;
use Ark4ne\JsonApi\Resources\Relationship;
use Ark4ne\JsonApi\Support\Values;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Http\Resources\PotentiallyMissing;

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

        return $this->autoWhenHas(
            $this->revised($this->toAttributes($context->request), $context),
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
            Values::mergeValues($this->revised($this->toRelationships($context->request), $context)),
        );
        $definitions = Definitions::for(static::class, $context->revision)->relationships;
        $model = $this->resource;

        foreach ($relationships as $key => $relationship) {
            if ($relationship instanceof PotentiallyMissing && $relationship->isMissing()) {
                continue;
            }

            $name = (string) Describer::retrieveName($relationship, $key);
            $definition = $definitions[$name] ?? null;

            if ($relationship instanceof RelationRaw) {
                // A relationship of `applyWhen()`: rendered without data when its condition fails, as a descriptor is.
                $raw = $relationship->relationship();
                if (!$raw instanceof Relationship) {
                    continue;
                }
                $relationship = $relationship->passes($context->request, $model, $name)
                    ? $raw
                    : $raw->withValue(static fn() => new MissingValue());
            } elseif ($relationship instanceof Relation) {
                // A resource guessed from the model is that of the definition: guessed once, when compiled.
                $relationship = Relationship::declared($relationship
                    ->resolveRelated($name, $definition?->related[0] ?? $definition?->native)
                    ->resolveFor($context->request, $model, $name, $context->configuration));
            } elseif (!$relationship instanceof Relationship) {
                continue;
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

        return $this->autoWhenHas(
            $this->revised($this->toResourceMeta($context->request) ?? [], $context),
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
            $this->revised($this->toDocumentMeta($context->request) ?? [], $context),
            $context->configuration->autoWhenHas('meta'),
        );
    }

    /**
     * The members of a hook as the revision of the rendering sees them (`RevisedMembers`), before anything reads a name:
     * the sparse fieldsets and the includes are written in the names of the revision. A resource declaring no revision
     * gives its members as they are, whatever the revision; a revised one at the head only loses its fields `until()` a
     * revision.
     *
     * @param iterable<array-key, mixed> $members
     *
     * @return iterable<array-key, mixed>
     */
    private function revised(iterable $members, JsonApiContext $context): iterable
    {
        return Definitions::isRevised(static::class) ? RevisedMembers::apply($members, $context->revision) : $members;
    }
}
