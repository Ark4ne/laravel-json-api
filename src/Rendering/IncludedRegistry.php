<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Rendering;

use Ark4ne\JsonApi\Context\JsonApiContext;

/**
 * The resources of `included`, keyed by `type:id` (pattern Identity Map, docs/architecture.md §8). One per document.
 *
 * - O(1) insertion; insertion order is kept.
 * - The primary resources are never included.
 * - A resource reached through two include paths is listed once, with the relationships rendered on both paths.
 */
final class IncludedRegistry
{
    /** @var array<string, ResourceObject> */
    private array $objects = [];

    /** @var array<string, true> */
    private array $primaries = [];

    /** @var array<string, true> The resources already rendered, by `type:id@path` */
    private array $rendered = [];

    public function __construct(
        private readonly ResourceObjectRenderer $renderer,
    ) {}

    /**
     * Marks a primary resource: it is never added to `included`.
     */
    public function primary(string $key): void
    {
        $this->primaries[$key] = true;
    }

    /**
     * Renders a related resource in the context of its include path and adds it. A primary resource is rendered too,
     * for the resources it includes in turn, but never added.
     */
    public function include(RenderableResource $resource, JsonApiContext $context): void
    {
        $key = $this->renderer->identify($resource, $context)->key();
        $pass = "{$key}@{$context->path}";

        if (isset($this->rendered[$pass])) {
            return;
        }

        $this->rendered[$pass] = true;

        if (isset($this->primaries[$key])) {
            $this->renderer->render($resource, $context, $this);

            return;
        }

        // The slot is taken before rendering: a resource precedes the resources it includes.
        $existing = $this->objects[$key] ?? null;
        $this->objects[$key] ??= $this->renderer->identify($resource, $context);

        $object = $this->renderer->render($resource, $context, $this);

        $this->objects[$key] = $existing === null ? $object : $existing->merge($object);
    }

    /**
     * @return list<ResourceObject>
     */
    public function all(): array
    {
        return array_values($this->objects);
    }
}
