<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Laravel\Commands;

use Ark4ne\ApiRevisions\Checking\Checker;
use Ark4ne\JsonApi\Compilation\DefinitionCompiler;
use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Compilation\ResourceChecker;
use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\DefinitionSource;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Laravel\ResourceFinder;
use Ark4ne\JsonApi\Support\Diagnostic;
use Ark4ne\JsonApi\Support\DiagnosticLevel;
use Illuminate\Console\Command;
use Illuminate\Routing\Router;

/**
 * Checks the resources and prints their findings, grouped by resource, with the revision showing them
 * (`Compilation\ResourceChecker`): what this package compiles (the type, the lifetime, the links), and the check of
 * `ark4ne/laravel-api-revisions` on them, the one `revision:check` runs on every declaring class: its own checks (the
 * history of the fields, the conversions, the lock), `Compilation\ResourceCheck` (the rules of JSON:API) and those the
 * application tags, run on the resources checked only (`Checker::forClasses()`).
 *
 * Fails on an error, or when the `self` route of `#[JsonApiRoutes]` does not exist; with `--strict`, also on the first
 * warning. `--revision=Labels` (the name of a case) keeps the findings of that revision and those of no revision in
 * particular; a resource absent from it is skipped.
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

        $skipped = [];
        foreach ($classes as $class) {
            try {
                if ($at !== null && is_subclass_of($class, DefinitionSource::class) && !$compiler->lifetime($class)->covers($at)) {
                    $skipped[] = $class;
                }
            } catch (DefinitionException) {
                // Reported by the check.
            }
        }

        // The check of `revision:check` (its checks, those the application tags, the lock), on these resources only.
        $checker = new ResourceChecker($compiler, $this->laravel->make(Checker::class));
        $found = $checker->check(array_values(array_diff($classes, $skipped)), $at);
        $failed = false;

        // The resources in the order given, then what the check found elsewhere (the revision enum).
        foreach ([...$classes, ...array_diff(array_keys($found), $classes)] as $class) {
            if (in_array($class, $skipped, true)) {
                $this->components->twoColumnDetail($class, '<fg=gray;options=bold>SKIPPED</>');

                continue;
            }

            $diagnostics = $found[$class] ?? [];

            try {
                $self = is_subclass_of($class, DefinitionSource::class) ? $class::declaredRoutes()?->self : null;
            } catch (DefinitionException) {
                $self = null;
            }
            if ($self !== null && !$router->has($self)) {
                // Every rendering of the resource would throw.
                $diagnostics[] = new Diagnostic(
                    DiagnosticLevel::Error,
                    self::MISSING_ROUTE,
                    "The route [{$self}] of #[JsonApiRoutes] does not exist.",
                    'self',
                );
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
