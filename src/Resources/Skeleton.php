<?php

namespace Ark4ne\JsonApi\Resources;

/**
 * @template T
 */
class Skeleton
{
    /**
     * @param class-string<T>     $for
     * @param string              $type
     * @param string[]            $fields
     * @param array<string, self> $relationships
     */
    public function __construct(
        public string $for,
        public string $type,
        public array $fields = [],
        public array $relationships = [],
    ) {
    }
}
