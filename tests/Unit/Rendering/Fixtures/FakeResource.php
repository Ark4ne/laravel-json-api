<?php

declare(strict_types=1);

namespace Test\Unit\Rendering\Fixtures;

use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Rendering\RelationshipSource;
use Ark4ne\JsonApi\Rendering\RenderableResource;

/**
 * A renderable resource given its members: the rendering is tested without the `Resources` layer.
 */
final class FakeResource implements RenderableResource
{
    /**
     * @param iterable<array-key, mixed> $attributes
     * @param array<string, RelationshipSource> $relationships
     * @param iterable<array-key, mixed> $links
     * @param iterable<array-key, mixed> $meta
     * @param iterable<array-key, mixed> $documentMeta
     */
    public function __construct(
        private readonly string $type,
        private readonly int|string $id,
        private readonly iterable $attributes = [],
        public array $relationships = [],
        private readonly iterable $links = [],
        private readonly iterable $meta = [],
        private readonly iterable $documentMeta = [],
        private readonly mixed $model = null,
    ) {}

    public function renderType(JsonApiContext $context): string
    {
        return $this->type;
    }

    public function renderIdentifier(JsonApiContext $context): int|string
    {
        return $this->id;
    }

    public function renderedModel(): mixed
    {
        return $this->model;
    }

    public function renderAttributes(JsonApiContext $context): iterable
    {
        return $this->attributes;
    }

    public function renderRelationships(JsonApiContext $context): iterable
    {
        return $this->relationships;
    }

    public function renderLinks(JsonApiContext $context): iterable
    {
        return $this->links;
    }

    public function renderResourceMeta(JsonApiContext $context): iterable
    {
        return $this->meta;
    }

    public function renderDocumentMeta(JsonApiContext $context): iterable
    {
        return $this->documentMeta;
    }
}
