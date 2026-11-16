<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Compilation;

use Ark4ne\JsonApi\Definition\ResourceDefinition;
use RuntimeException;

/**
 * The file of compiled definitions (`bootstrap/cache/jsonapi.php`): a PHP file returning the definitions exported with
 * `var_export()`, read through OPcache (docs/architecture.md §4).
 *
 * Keyed by class for the head, `class@Case` for the definition of a revision (`key()`); a file of 2.0 has heads only.
 */
final readonly class CompiledDefinitions
{
    public function __construct(
        public string $path,
    ) {}

    /**
     * The key of a definition in the file: its class, followed by `@` and the name of its revision unless it describes
     * the head.
     */
    public static function key(ResourceDefinition $definition): string
    {
        return $definition->revision === null ? $definition->class : "{$definition->class}@{$definition->revision->name}";
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    /**
     * @return array<string, ResourceDefinition>
     */
    public function read(): array
    {
        if (!$this->exists()) {
            return [];
        }

        /** @var array<string, ResourceDefinition> */
        return require $this->path;
    }

    /**
     * Writes the file atomically (temporary file, then rename).
     *
     * @param array<string, ResourceDefinition> $definitions
     */
    public function write(array $definitions): void
    {
        $directory = dirname($this->path);
        // Failures are reported by the return values: the warnings would only repeat them.
        if (!is_dir($directory) && !@mkdir($directory, 0o755, true) && !is_dir($directory)) {
            throw new RuntimeException("Cannot create the directory [{$directory}].");
        }

        // tempnam() falls back to the system directory when it cannot write here: the rename would then cross devices.
        $temporary = @tempnam($directory, 'jsonapi');
        if ($temporary === false || realpath(dirname($temporary)) !== realpath($directory)) {
            if ($temporary !== false) {
                @unlink($temporary);
            }

            throw new RuntimeException("Cannot write in the directory [{$directory}].");
        }

        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($definitions, true) . ";\n";

        if (
            @file_put_contents($temporary, $content) !== strlen($content)
            || !@chmod($temporary, 0o644)
            || !@rename($temporary, $this->path)
        ) {
            @unlink($temporary);

            throw new RuntimeException("Cannot write the compiled definitions to [{$this->path}].");
        }

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($this->path, true);
        }
    }

    /**
     * Deletes the file; `false` when there was none.
     */
    public function delete(): bool
    {
        return $this->exists() && unlink($this->path);
    }
}
