<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Laravel\Commands;

use Ark4ne\JsonApi\Compilation\DefinitionCompiler;
use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\DefinitionSource;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Laravel\ResourceFinder;
use Ark4ne\JsonApi\Support\Diagnostic;
use Ark4ne\JsonApi\Support\DiagnosticLevel;
use Illuminate\Console\Command;

/**
 * Compiles the definitions of the resources and prints their diagnostics, grouped by resource; replaces
 * `Asserts\AssertJsonApiResource`.
 *
 * Fails when a definition cannot be compiled or has an error; with `--strict`, also on the first warning.
 */
final class CheckDefinitionsCommand extends Command
{
    /** @var string */
    protected $signature = 'jsonapi:check
        {resources?* : Resource classes to check (default: every resource found in definitions.paths)}
        {--strict : Fail on warnings too}';

    /** @var string */
    protected $description = 'Check the JSON:API resource definitions and print their diagnostics';

    public function handle(Configuration $configuration, ResourceFinder $finder): int
    {
        /** @var list<string> $requested */
        $requested = $this->argument('resources');
        $classes = $requested !== [] ? $requested : $finder->find();
        $strict = (bool) $this->option('strict');
        $compiler = new DefinitionCompiler($configuration);
        $failed = false;

        foreach ($classes as $class) {
            try {
                if (!is_subclass_of($class, DefinitionSource::class)) {
                    throw DefinitionException::notADefinitionSource($class);
                }

                $diagnostics = $compiler->compile($class)->diagnostics;
            } catch (DefinitionException $e) {
                $this->components->twoColumnDetail($class, '<fg=red;options=bold>ERROR</>');
                $this->line("  ⇂ {$e->getMessage()}");
                $failed = true;

                continue;
            }

            $levels = array_map(static fn(Diagnostic $diagnostic): DiagnosticLevel => $diagnostic->level, $diagnostics);
            $status = match (true) {
                in_array(DiagnosticLevel::Error, $levels, true) => '<fg=red;options=bold>ERROR</>',
                in_array(DiagnosticLevel::Warning, $levels, true) => '<fg=yellow;options=bold>WARN</>',
                default => '<fg=green;options=bold>OK</>',
            };
            $failed = $failed
                || in_array(DiagnosticLevel::Error, $levels, true)
                || $strict && in_array(DiagnosticLevel::Warning, $levels, true);

            $this->components->twoColumnDetail($class, $status);

            foreach ($diagnostics as $diagnostic) {
                // One line per diagnostic, never wrapped: the output is meant to be searched and copied.
                $this->line(
                    "  ⇂ [{$diagnostic->level->value}] {$diagnostic->code}"
                    . ($diagnostic->subject === null ? '' : " ({$diagnostic->subject})")
                    . ": {$diagnostic->message}",
                );
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
