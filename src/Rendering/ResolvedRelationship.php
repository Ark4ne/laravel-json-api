<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Rendering;

use Ark4ne\JsonApi\Definition\Cardinality;

/**
 * A relationship of one resource once resolved: what its relationship object is rendered from.
 */
final readonly class ResolvedRelationship
{
    /**
     * @param RenderableResource|list<RenderableResource>|null $data The related resources; `null` for an empty to-one
     * @param bool $hasData Whether the data is known; `false` when it is left out (condition not met, not included)
     * @param array<array-key, mixed> $links
     * @param array<array-key, mixed> $meta
     */
    private function __construct(
        public Cardinality $cardinality,
        public RenderableResource|array|null $data,
        public bool $hasData,
        public array $links,
        public array $meta,
    ) {}

    /**
     * @param array<array-key, mixed> $links
     * @param array<array-key, mixed> $meta
     */
    public static function toOne(?RenderableResource $data, array $links = [], array $meta = []): self
    {
        return new self(Cardinality::One, $data, true, $links, $meta);
    }

    /**
     * @param list<RenderableResource> $data
     * @param array<array-key, mixed> $links
     * @param array<array-key, mixed> $meta
     */
    public static function toMany(array $data, array $links = [], array $meta = []): self
    {
        return new self(Cardinality::Many, $data, true, $links, $meta);
    }

    /**
     * A relationship whose data is left out: only its links and meta are rendered, if any.
     *
     * @param array<array-key, mixed> $links
     * @param array<array-key, mixed> $meta
     */
    public static function withoutData(Cardinality $cardinality, array $links = [], array $meta = []): self
    {
        return new self($cardinality, null, false, $links, $meta);
    }

    /**
     * The related resources as a list, whatever the cardinality.
     *
     * @return list<RenderableResource>
     */
    public function resources(): array
    {
        return match (true) {
            is_array($this->data) => $this->data,
            $this->data === null => [],
            default => [$this->data],
        };
    }
}
