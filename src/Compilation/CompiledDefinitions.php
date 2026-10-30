<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Compilation;

use Ark4ne\JsonApi\Definition\DefinitionSource;
use Ark4ne\JsonApi\Definition\ResourceDefinition;

/**
 * The file of compiled definitions (`bootstrap/cache/jsonapi.php`): a PHP file returning the definitions exported with
 * `var_export()`, read through OPcache (docs/architecture.md §4).
 */
final readonly class CompiledDefinitions
{
    public function __construct(
        public string $path,
    ) {}

    public function exists(): bool
    {
        return false;
    }

    /**
     * @return array<class-string<DefinitionSource>, ResourceDefinition>
     */
    public function read(): array
    {
        return [];
    }

    /**
     * Writes the file atomically (temporary file, then rename).
     *
     * @param array<class-string<DefinitionSource>, ResourceDefinition> $definitions
     */
    public function write(array $definitions): void {}

    /**
     * Deletes the file; `false` when there was none.
     */
    public function delete(): bool
    {
        return false;
    }
}
