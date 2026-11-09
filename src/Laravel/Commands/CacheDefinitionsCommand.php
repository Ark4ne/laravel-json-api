<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Laravel\Commands;

use Ark4ne\JsonApi\Compilation\CompiledDefinitions;
use Ark4ne\JsonApi\Compilation\DefinitionCompiler;
use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Laravel\ResourceFinder;
use Ark4ne\JsonApi\Support\DiagnosticLevel;
use Illuminate\Console\Command;

/**
 * Compiles the definitions of every resource of the application into `bootstrap/cache/jsonapi.php`: the head, and one
 * per revision where its shape changes.
 *
 * Writes nothing when a definition cannot be compiled or has an error: `jsonapi:check` tells why.
 */
final class CacheDefinitionsCommand extends Command
{
    /** @var string */
    protected $signature = 'jsonapi:cache';

    /** @var string */
    protected $description = 'Compile the JSON:API resource definitions into a cache file';

    public function handle(Configuration $configuration, ResourceFinder $finder, CompiledDefinitions $compiled): int
    {
        $compiler = new DefinitionCompiler($configuration);
        $definitions = [];
        $resources = [];
        $invalid = [];

        foreach ($finder->find() as $class) {
            try {
                $every = $compiler->compileEvery($class);
            } catch (DefinitionException) {
                $invalid[] = $class;

                continue;
            }

            foreach ($every as $definition) {
                $definitions[CompiledDefinitions::key($definition)] = $definition;
                $resources[$class] = true;
            }

            // The head carries the diagnostics of every revision.
            foreach ($every[0]->diagnostics as $diagnostic) {
                if ($diagnostic->level === DiagnosticLevel::Error) {
                    $invalid[] = $class;

                    break;
                }
            }
        }

        if ($invalid !== []) {
            $this->components->error('JSON:API definitions not cached: these resources have errors (see jsonapi:check).');
            $this->components->bulletList($invalid);

            return self::FAILURE;
        }

        $compiled->write($definitions);
        Definitions::flush();

        $this->components->info(sprintf(
            'JSON:API definitions cached successfully (%d resources, %d definitions).',
            count($resources),
            count($definitions),
        ));

        return self::SUCCESS;
    }
}
