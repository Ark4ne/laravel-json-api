<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Compilation;

use Ark4ne\ApiRevisions\Checking\Check;
use Ark4ne\ApiRevisions\Checking\Checker;
use Ark4ne\ApiRevisions\Compilation\Compiler;
use Ark4ne\ApiRevisions\Revisions\Revision;
use Ark4ne\ApiRevisions\Revisions\Unrevisioned;
use Ark4ne\ApiRevisions\Schema\Revisioned;
use Ark4ne\ApiRevisions\Support\Severity;
use Ark4ne\JsonApi\Definition\DefinitionSource;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Support\Diagnostic;
use Ark4ne\JsonApi\Support\DiagnosticLevel;

/**
 * Checks resources (docs/rfc/api-revisions-integration.md D13), for `jsonapi:check` and `ResourceAssertions`:
 *
 * - what only this package compiles, on the definition of each resource at every revision it exists at: its type,
 *   its lifetime, its links (`hook-failure`), its relationships (`invalid-declaration`), a class that is not a
 *   resource (`invalid-resource`);
 * - everything else with the check of `ark4ne/laravel-api-revisions` (its `Checker`, the one of `revision:check`),
 *   revision by revision: the history of the fields, the conversions, and the rules of JSON:API (`ResourceCheck`).
 *
 * Its findings are those of this package (`Support\Diagnostic`), named by the revision showing them.
 */
final readonly class ResourceChecker
{
    /** The class is not a resource, or its definition cannot be compiled at all (its type, its lifetime). */
    public const INVALID_RESOURCE = 'invalid-resource';

    /** @var list<Check> */
    private array $checks;

    /**
     * @param list<Check>|null $checks The checks run on each resource; those of `ark4ne/laravel-api-revisions` and
     *                                 `ResourceCheck` when `null`
     * @param Compiler $compiler The compiler of the base the shapes are checked with
     */
    public function __construct(
        private DefinitionCompiler $definitions,
        ?array $checks = null,
        private Compiler $compiler = new Compiler(),
    ) {
        $this->checks = $checks ?? [...Checker::builtIn(), new ResourceCheck()];
    }

    /**
     * The findings about each class, and about the revision enum under its own name when it has some.
     *
     * @param list<string> $classes
     * @param Revision|null $only Keeps the findings of this revision and those of no revision in particular
     *
     * @return array<string, list<Diagnostic>>
     */
    public function check(array $classes, ?Revision $only = null): array
    {
        $found = [];
        $resources = [];
        foreach ($classes as $class) {
            try {
                if (!is_subclass_of($class, DefinitionSource::class)) {
                    throw DefinitionException::notADefinitionSource($class);
                }

                $found[$class] = $this->compiled($class, $only);
                if (is_subclass_of($class, Revisioned::class)) {
                    $resources[] = $class;
                }
            } catch (DefinitionException $e) {
                $found[$class] = [new Diagnostic(DiagnosticLevel::Error, self::INVALID_RESOURCE, $e->getMessage())];
            }
        }

        $enum = $this->definitions->head()::class;
        $names = [];
        foreach ($enum::cases() as $case) {
            // A revision of an application without revision enum is no revision in particular.
            $names[(string) $case->value] = $enum === Unrevisioned::class ? null : $case->name;
        }

        $checked = [];
        foreach ((new Checker($enum, new ListedClasses($resources), $this->checks, $this->compiler))->run() as $diagnostic) {
            $revision = $diagnostic->revision;
            if ($revision !== null && array_key_exists($revision, $names)) {
                $revision = $names[$revision];
            }
            if ($only !== null && $revision !== null && $revision !== $only->name) {
                continue;
            }

            $checked[$diagnostic->class][] = new Diagnostic(
                $diagnostic->severity === Severity::Error ? DiagnosticLevel::Error : DiagnosticLevel::Warning,
                $diagnostic->code,
                $diagnostic->message,
                $diagnostic->field,
                $revision,
            );
        }

        foreach ($checked as $class => $diagnostics) {
            // The check of the base names what it cannot compile: the finding of the definition repeating it is left
            // out, the one of what only this package compiles (its relationships) is kept.
            $compiled = array_values(array_filter(
                $found[$class] ?? [],
                static fn(Diagnostic $diagnostic): bool => $diagnostic->code !== DefinitionCompiler::INVALID_DECLARATION
                    || !self::repeats($diagnostic, $diagnostics),
            ));
            $found[$class] = [...$compiled, ...$diagnostics];
        }

        return $found;
    }

    /**
     * The findings of the definitions of a resource, compiled at every revision it exists at: each one once, named by
     * the newest revision showing it (none for the head).
     *
     * @param class-string<DefinitionSource> $class
     *
     * @return list<Diagnostic>
     *
     * @throws DefinitionException
     */
    private function compiled(string $class, ?Revision $only): array
    {
        $found = [];
        foreach ($this->definitions->compileEvery($class) as $definition) {
            foreach ($definition->diagnostics as $diagnostic) {
                $found[$diagnostic->key()] ??= $diagnostic;
            }
        }

        return array_values(array_filter(
            $found,
            static fn(Diagnostic $diagnostic): bool => $only === null || $diagnostic->revision === null || $diagnostic->revision === $only->name,
        ));
    }

    /**
     * Whether a finding of the base already gives the reason `$diagnostic` reports.
     *
     * @param list<Diagnostic> $checked
     */
    private static function repeats(Diagnostic $diagnostic, array $checked): bool
    {
        foreach ($checked as $finding) {
            if ($finding->message !== '' && str_contains($diagnostic->message, $finding->message)) {
                return true;
            }
        }

        return false;
    }
}
