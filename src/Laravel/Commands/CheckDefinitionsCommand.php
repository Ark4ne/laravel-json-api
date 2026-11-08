<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Laravel\Commands;

use Ark4ne\JsonApi\Compilation\DefinitionCompiler;
use Ark4ne\JsonApi\Compilation\RevisionAudit;
use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\DefinitionSource;
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
        $audit = new RevisionAudit(new DefinitionCompiler($configuration));
        $failed = false;
        $known = false;

        foreach ($classes as $class) {
            try {
                if (!is_subclass_of($class, DefinitionSource::class)) {
                    throw DefinitionException::notADefinitionSource($class);
                }

                $definition = $audit->shapes($class)[0];

                if ($only === null) {
                    $diagnostics = [...$definition->diagnostics, ...$audit->check($class)];
                } else {
                    $revisions = self::named($audit, $class, $only);
                    $known = $known || $revisions !== [];
                    // Declaring no revision, it has its head shape at every one.
                    $diagnostics = self::diagnosticsAt(
                        $audit,
                        $class,
                        $revisions === [] && $audit->enum($class) === null ? [null] : $revisions,
                    );
                    if ($diagnostics === null) {
                        $this->components->twoColumnDetail($class, '<fg=gray;options=bold>SKIPPED</>');

                        continue;
                    }
                }

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
     * The diagnostics of the resource at the revisions, and its references there, each once; `null` when it exists at
     * none of them.
     *
     * @param class-string<DefinitionSource> $class
     * @param list<Revision|null> $revisions
     *
     * @return list<Diagnostic>|null
     */
    private static function diagnosticsAt(RevisionAudit $audit, string $class, array $revisions): ?array
    {
        $diagnostics = null;
        foreach ($revisions as $revision) {
            $at = $audit->diagnosticsAt($class, $revision);
            if ($at === null) {
                continue;
            }

            $diagnostics ??= [];
            foreach ([...$at, ...$audit->check($class, [$revision])] as $diagnostic) {
                $diagnostics["{$diagnostic->code}|{$diagnostic->subject}|{$diagnostic->message}"] ??= $diagnostic;
            }
        }

        return $diagnostics === null ? null : array_values($diagnostics);
    }

    /**
     * The revisions named `$name` a resource is checked at: the case of the enum it declares with, otherwise those of
     * every enum its related resources declare with (each is checked). None for a resource declaring revisions with an
     * enum without that case, nor for a resource declaring none whose related resources know no such case.
     *
     * @param class-string<DefinitionSource> $class
     *
     * @return list<Revision>
     */
    private static function named(RevisionAudit $audit, string $class, string $name): array
    {
        $enum = $audit->enum($class);
        $named = [];
        foreach ($audit->revisions($class) as $revision) {
            if ($revision !== null && $revision->name === $name && ($enum === null || $revision instanceof $enum)) {
                $named[] = $revision;
            }
        }

        return $named;
    }
}
