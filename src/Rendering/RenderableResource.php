<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Rendering;

use Ark4ne\JsonApi\Context\JsonApiContext;

/**
 * A resource for one model, as the renderer reads it (docs/architecture.md §8).
 *
 * `JsonApiResource` implements it through `@internal` bridges to its hooks: the renderer never depends on the
 * `Resources` layer.
 */
interface RenderableResource
{
    public function renderType(JsonApiContext $context): string;

    public function renderIdentifier(JsonApiContext $context): int|string;

    /**
     * The model (or array, DTO) the descriptors read.
     */
    public function renderedModel(): mixed;

    /**
     * The members of `toAttributes()`, prepared (`autoWhenHas`) but not resolved: descriptors, closures, values.
     *
     * @return iterable<array-key, mixed>
     */
    public function renderAttributes(JsonApiContext $context): iterable;

    /**
     * The relationships of `toRelationships()` by name, prepared (`autoWhenIncluded`).
     *
     * @return iterable<string, RelationshipSource>
     */
    public function renderRelationships(JsonApiContext $context): iterable;

    /**
     * The members of `toLinks()`, not resolved.
     *
     * @return iterable<array-key, mixed>
     */
    public function renderLinks(JsonApiContext $context): iterable;

    /**
     * The members of `toResourceMeta()`, prepared (`autoWhenHas`) but not resolved.
     *
     * @return iterable<array-key, mixed>
     */
    public function renderResourceMeta(JsonApiContext $context): iterable;

    /**
     * The members of the top-level `meta` this resource adds to its document, not resolved.
     *
     * @return iterable<array-key, mixed>
     */
    public function renderDocumentMeta(JsonApiContext $context): iterable;
}
