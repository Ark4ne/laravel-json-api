<?php

namespace Ark4ne\JsonApi\Resources;

/**
 * @template T
 */
class Skeleton
{
    /**
     * @param class-string $for
     * @param string $type
     * @param string[] $fields
     * @param array<string, self> $relationships
     * @param array<string, false|string|mixed> $loads
     */
    public function __construct(
        public string $for,
        public string $type,
        public array $fields = [],
        public array $relationships = [],
        public array $loads = [],
    ) {
    }
}
