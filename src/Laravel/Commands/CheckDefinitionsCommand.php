<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Laravel\Commands;

use Ark4ne\JsonApi\Compilation\DefinitionCompiler;
use Ark4ne\JsonApi\Compilation\RevisionReferences;
use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\DefinitionSource;
use Ark4ne\JsonApi\Definition\ResourceDefinition;
use Ark4ne\JsonApi\Definition\Revision;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Laravel\ResourceFinder;
use Ark4ne\JsonApi\Support\Diagnostic;
use Ark4ne\JsonApi\Support\DiagnosticLevel;
use Illuminate\Console\Command;
use Illuminate\Routing\Router;

/**
 * Compiles the definitions of the resources at every revision and prints their diagnostics, grouped by resource, with
 * the revision whose shape shows them; then checks that their relationships reach, at every revision, resources
 * existing at it (`revision-unavailable-resource`, `RevisionReferences`).
 *
 * Fails when a definition cannot be compiled or has an error, or when the `self` route of `#[JsonApiRoutes]` does not
 * exist; with `--strict`, also on the first warning. `--revision=Labels` (the name of a case) restricts the check to
 * the shape of that revision and to its references; a resource that does not know it, or does not exist at it, is
 * skipped.
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
        $compiler = new DefinitionCompiler($configuration);
        $references = new RevisionReferences($compiler);
        $failed = false;
        $known = false;

        foreach ($classes as $class) {
            try {
                if (!is_subclass_of($class, DefinitionSource::class)) {
                    throw DefinitionException::notADefinitionSource($class);
                }

                $definition = $compiler->compile($class);
                $diagnostics = $definition->diagnostics;
                $at = null;

                if ($only !== null) {
                    $revision = self::find($references->revisions($class), $only);
                    $boundary = $revision === null ? false : self::boundary($definition, $revision);
                    if ($revision === null || $boundary === false) {
                        $this->components->twoColumnDetail($class, '<fg=gray;options=bold>SKIPPED</>');

                        continue;
                    }

                    $known = true;
                    $at = [$revision];
                    // The diagnostics of the shape of that revision, which those of the declarations are read with
                    // when it is the head shape.
                    $diagnostics = array_values(array_filter(
                        $diagnostics,
                        static fn(Diagnostic $diagnostic): bool => $diagnostic->revision === $boundary,
                    ));
                }

                array_push($diagnostics, ...$references->check($class, $at));

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

        if ($only !== null && !$known) {
            $this->components->error("No resource checked exists at a revision named [{$only}].");

            return self::FAILURE;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * The name of the boundary whose shape the resource has at the revision (`null` for the head shape); `false` when
     * it does not exist at it, or declares its revisions with another enum.
     */
    private static function boundary(ResourceDefinition $definition, Revision $revision): string|false|null
    {
        try {
            return $definition->existsAt($revision) ? $definition->boundaryOf($revision)?->name : false;
        } catch (DefinitionException) {
            return false;
        }
    }

    /**
     * The revision named `$name` among those of a resource.
     *
     * @param list<Revision|null> $revisions
     */
    private static function find(array $revisions, string $name): ?Revision
    {
        foreach ($revisions as $revision) {
            if ($revision?->name === $name) {
                return $revision;
            }
        }

        return null;
    }
}
