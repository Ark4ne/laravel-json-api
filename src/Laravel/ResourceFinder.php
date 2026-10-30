<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Laravel;

use Ark4ne\JsonApi\Definition\DefinitionSource;

/**
 * Finds the resource classes of an application, for `jsonapi:cache` and `jsonapi:check`.
 *
 * Reads the namespace and the class declared by every PHP file of the directories (tokens, no `require`), then keeps
 * the concrete classes implementing `DefinitionSource`.
 */
final readonly class ResourceFinder
{
    /**
     * @param list<string> $paths Directories to scan, recursively
     */
    public function __construct(
        private array $paths,
    ) {}

    /**
     * @return list<class-string<DefinitionSource>>
     */
    public function find(): array
    {
        return [];
    }
}
