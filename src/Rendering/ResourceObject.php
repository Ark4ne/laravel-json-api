<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Rendering;

/**
 * A rendered resource object (https://jsonapi.org/format/#document-resource-objects), built stage by stage.
 *
 * Shape guarantees hold by construction: `id` is a string, empty members are left out.
 */
final readonly class ResourceObject
{
    /**
     * @param array<array-key, mixed> $attributes
     * @param array<string, array{data?: array{type: string, id: string}|list<array{type: string, id: string}>|null, links?: array<array-key, mixed>, meta?: array<array-key, mixed>}> $relationships
     * @param array<array-key, mixed> $links
     * @param array<array-key, mixed> $meta
     */
    private function __construct(
        public string $type,
        public string $id,
        public array $attributes = [],
        public array $relationships = [],
        public array $links = [],
        public array $meta = [],
    ) {}

    public static function identify(string $type, int|string $id): self
    {
        return new self($type, (string) $id);
    }

    /**
     * @param array<array-key, mixed> $attributes
     */
    public function withAttributes(array $attributes): self
    {
        return $this->copy(attributes: $attributes);
    }

    /**
     * @param array<string, array{data?: array{type: string, id: string}|list<array{type: string, id: string}>|null, links?: array<array-key, mixed>, meta?: array<array-key, mixed>}> $relationships
     */
    public function withRelationships(array $relationships): self
    {
        return $this->copy(relationships: $relationships);
    }

    /**
     * @param array<array-key, mixed> $links
     */
    public function withLinks(array $links): self
    {
        return $this->copy(links: $links);
    }

    /**
     * @param array<array-key, mixed> $meta
     */
    public function withMeta(array $meta): self
    {
        return $this->copy(meta: $meta);
    }

    /**
     * The identity map key: `type:id`.
     */
    public function key(): string
    {
        return "{$this->type}:{$this->id}";
    }

    /**
     * @return array{type: string, id: string}
     */
    public function identifier(): array
    {
        return ['type' => $this->type, 'id' => $this->id];
    }

    /**
     * The same resource rendered through another include path: the relationships it renders there are added.
     */
    public function merge(self $other): self
    {
        $relationships = $this->relationships;

        foreach ($other->relationships as $name => $relationship) {
            $merged = array_replace($relationships[$name] ?? [], $relationship);
            $ordered = [];

            // In the order of the specification: data, links, meta.
            if (array_key_exists('data', $merged)) {
                $ordered['data'] = $merged['data'];
            }
            if (isset($merged['links'])) {
                $ordered['links'] = $merged['links'];
            }
            if (isset($merged['meta'])) {
                $ordered['meta'] = $merged['meta'];
            }

            $relationships[$name] = $ordered;
        }

        return $this->copy(relationships: $relationships);
    }

    /**
     * @return array{type: string, id: string, attributes?: array<array-key, mixed>, relationships?: array<string, array{data?: array{type: string, id: string}|list<array{type: string, id: string}>|null, links?: array<array-key, mixed>, meta?: array<array-key, mixed>}>, links?: array<array-key, mixed>, meta?: array<array-key, mixed>}
     */
    public function toArray(): array
    {
        return [
            ...$this->identifier(),
            ...array_filter([
                'attributes' => $this->attributes,
                'relationships' => $this->relationships,
                'links' => $this->links,
                'meta' => $this->meta,
            ], static fn(array $member) => $member !== []),
        ];
    }

    /**
     * @param array<array-key, mixed>|null $attributes
     * @param array<string, array{data?: array{type: string, id: string}|list<array{type: string, id: string}>|null, links?: array<array-key, mixed>, meta?: array<array-key, mixed>}>|null $relationships
     * @param array<array-key, mixed>|null $links
     * @param array<array-key, mixed>|null $meta
     */
    private function copy(
        ?array $attributes = null,
        ?array $relationships = null,
        ?array $links = null,
        ?array $meta = null,
    ): self {
        return new self(
            $this->type,
            $this->id,
            $attributes ?? $this->attributes,
            $relationships ?? $this->relationships,
            $links ?? $this->links,
            $meta ?? $this->meta,
        );
    }
}
