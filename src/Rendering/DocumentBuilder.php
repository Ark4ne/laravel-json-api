<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Rendering;

use Ark4ne\JsonApi\Context\JsonApiContext;

/**
 * Builds the document of a resource or a collection (pattern Builder, docs/architecture.md §8): primary data,
 * `included` through one `IncludedRegistry`, and the top-level `meta` the primary resources add (the first one setting a
 * key keeps it; included resources add none).
 */
final readonly class DocumentBuilder
{
    private ResourceObjectRenderer $renderer;

    /**
     * @param ResourceObjectRenderer|null $renderer The stages of the package when `null`
     */
    public function __construct(?ResourceObjectRenderer $renderer = null)
    {
        $this->renderer = $renderer ?? ResourceObjectRenderer::default();
    }

    public function resource(?RenderableResource $resource, JsonApiContext $context): Document
    {
        if ($resource === null) {
            return new Document(null);
        }

        [[$object], $included, $meta] = $this->render([$resource], $context);

        return new Document($object, $included, $meta);
    }

    /**
     * @param iterable<RenderableResource> $resources
     */
    public function collection(iterable $resources, JsonApiContext $context): Document
    {
        [$objects, $included, $meta] = $this->render(array_values([...$resources]), $context);

        return new Document($objects, $included, $meta);
    }

    /**
     * @param list<RenderableResource> $resources
     *
     * @return array{list<ResourceObject>, list<ResourceObject>, array<array-key, mixed>}
     */
    private function render(array $resources, JsonApiContext $context): array
    {
        $included = new IncludedRegistry($this->renderer);

        // Every primary resource is known before any is rendered: none of them is ever included.
        foreach ($resources as $resource) {
            $included->primary($this->renderer->identify($resource, $context)->key());
        }

        $objects = [];
        $meta = [];
        $values = new ValueResolver();

        foreach ($resources as $resource) {
            $objects[] = $this->renderer->render($resource, $context, $included);
            // The first resource setting a key keeps it.
            $meta += $values->resolve($resource->renderDocumentMeta($context), $resource->renderedModel(), $context);
        }

        return [$objects, $included->all(), $meta];
    }
}
