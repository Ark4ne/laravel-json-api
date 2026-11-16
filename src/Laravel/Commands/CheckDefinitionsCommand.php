<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Laravel\Commands;

use Ark4ne\ApiRevisions\Revisions\Revision;
use Ark4ne\JsonApi\Compilation\DefinitionCompiler;
use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\DefinitionSource;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Laravel\ResourceFinder;
use Ark4ne\JsonApi\Support\Diagnostic;
use Ark4ne\JsonApi\Support\DiagnosticLevel;
use Illuminate\Console\Command;
use Illuminate\Routing\Router;

/**
 * Compiles the definitions of the resources at every revision and prints their diagnostics, grouped by resource, with
 * the revision whose shape shows them; then checks that their relationships reach, at every revision, resources
 * existing at it (`revision-unavailable-resource`, `RevisionAudit`).
 *
 * Fails when a definition cannot be compiled or has an error, or when the `self` route of `#[JsonApiRoutes]` does not
 * exist; with `--strict`, also on the first warning. `--revision=Labels` (the name of a case) restricts the check to
 * the shape of that revision, its declarations and its references: every case of that name the resource may be
 * rendered at; a resource declaring no revision is checked at its head shape, the one it has at every revision. A
 * resource absent from that revision, or declaring its revisions with an enum without that case, is skipped; no
 * resource knowing the revision fails.
 */
final class CheckDefinitionsCommand extends Command
{
    /** The `self` route of `#[JsonApiRoutes]` does not exist: rendering the resource throws. */
    public const MISSING_ROUTE = 'missing-route';

    /** @var string */
    protected $signature = 'jsonapi:check
        {resources?* : Resource classes to check (default: every resource found in definitions.paths)}
        {--strict : Fail on warnings too}
        {--revision= : Check only this revision (the name of a case of the revision enum)}';

    /** @var string */
    protected $description = 'Check the JSON:API resource definitions and print their diagnostics';

    public function handle(Configuration $configuration, ResourceFinder $finder, Router $router): int
    {
        /** @var list<string> $requested */
        $requested = $this->argument('resources');
        $classes = $requested !== [] ? $requested : $finder->find();
        $strict = (bool) $this->option('strict');
        /** @var string|null $only */
        $only = $this->option('revision');
        $compiler = new DefinitionCompiler($configuration, Definitions::projection());
        $at = null;
        if ($only !== null) {
            foreach ($compiler->head()::cases() as $case) {
                $at = $case->name === $only ? $case : $at;
            }
            if ($at === null) {
                $this->components->error("No revision is named [{$only}].");

                return self::FAILURE;
            }
        }
        $failed = false;

        foreach ($classes as $class) {
            try {
                if (!is_subclass_of($class, DefinitionSource::class)) {
                    throw DefinitionException::notADefinitionSource($class);
                }

                if ($at !== null && !$compiler->lifetime($class)->covers($at)) {
                    $this->components->twoColumnDetail($class, '<fg=gray;options=bold>SKIPPED</>');

                    continue;
                }

                $definitions = $at === null ? $compiler->compileEvery($class) : [$compiler->compile($class, $at)];
                $definition = $definitions[0];
                $diagnostics = [];
                foreach ($definitions as $compiled) {
                    foreach ($compiled->diagnostics as $diagnostic) {
                        $diagnostics[$diagnostic->key()] ??= $diagnostic;
                    }
                }
                $diagnostics = array_values($diagnostics);

                $self = $definition->routes?->self;
                if ($self !== null && !$router->has($self)) {
                    // Every rendering of the resource would throw.
                    $diagnostics[] = new Diagnostic(
                        DiagnosticLevel::Error,
                        self::MISSING_ROUTE,
                        "The route [{$self}] of #[JsonApiRoutes] does not exist.",
                        'self',
                    );
                }
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
                $this->line("  ⇂ {$diagnostic->describe()}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
