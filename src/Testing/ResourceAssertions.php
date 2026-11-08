<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Testing;

use Ark4ne\JsonApi\Compilation\DefinitionCompiler;
use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Compilation\RevisionReferences;
use Ark4ne\JsonApi\Definition\DefinitionSource;
use Ark4ne\JsonApi\Definition\ResourceDefinition;
use Ark4ne\JsonApi\Definition\Revision;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Support\Diagnostic;
use Ark4ne\JsonApi\Support\DiagnosticLevel;
use PHPUnit\Framework\Assert;

/**
 * Assertions for the test suites of applications: `ResourceAssertions::assertValid(PostResource::class)`.
 *
 * Every definition is compiled from the current code: a cached or compiled definition may be stale. Requires PHPUnit,
 * which the application already has in its development dependencies.
 */
final class ResourceAssertions
{
    /**
     * Fails when the definition of the resource cannot be compiled or has an error; with `$strict`, also on a warning.
     * The relationships must reach resources existing at the revision (`revision-unavailable-resource`).
     *
     * At the head (`null`), the diagnostics are those of the head definition, which carries those of every revision,
     * as `jsonapi:check` prints them; at a revision, those of its shape only, and the resource must exist at it.
     *
     * @param class-string<DefinitionSource> $class
     */
    public static function assertValid(string $class, bool $strict = false, ?Revision $revision = null): void
    {
        self::assertDiagnostics($class, $strict, $revision === null ? [null] : [$revision], $revision !== null);
    }

    /**
     * `assertValid()` at every revision: the diagnostics of every shape, and the references between resources at the
     * head and at every case of the enums the resource and its related resources declare with.
     *
     * @param class-string<DefinitionSource> $class
     */
    public static function assertValidAtEveryRevision(string $class, bool $strict = false): void
    {
        self::assertDiagnostics($class, $strict, null, false);
    }

    /**
     * The definition of the resource at a revision, for snapshot tests: the shape of its bucket, compiled from the
     * current code. Fails when the resource does not exist at it.
     *
     * @param class-string<DefinitionSource> $class
     */
    public static function definitionAt(string $class, Revision $revision): ResourceDefinition
    {
        try {
            $definition = self::compiler()->compile($class, $revision);
            if (!$definition->existsAt($revision)) {
                throw DefinitionException::notInRevision($class, $revision::class . '::' . $revision->name);
            }
        } catch (DefinitionException $e) {
            Assert::fail($e->getMessage());
        }

        return $definition;
    }

    /**
     * @param class-string<DefinitionSource> $class
     * @param list<Revision|null>|null $revisions The revisions whose references are checked, every one when `null`
     * @param bool $shape Whether to keep only the diagnostics of the shape of the single revision given
     */
    private static function assertDiagnostics(string $class, bool $strict, ?array $revisions, bool $shape): void
    {
        $compiler = self::compiler();

        try {
            $definition = $compiler->compile($class);
            $diagnostics = $definition->diagnostics;

            $revision = $revisions[0] ?? null;
            if ($shape && $revision !== null) {
                if (!$definition->existsAt($revision)) {
                    throw DefinitionException::notInRevision($class, $revision::class . '::' . $revision->name);
                }

                $boundary = $definition->boundaryOf($revision)?->name;
                $diagnostics = array_filter(
                    $diagnostics,
                    static fn(Diagnostic $diagnostic): bool => $diagnostic->revision === $boundary,
                );
            }
        } catch (DefinitionException $e) {
            Assert::fail($e->getMessage());
        }

        $diagnostics = [...$diagnostics, ...(new RevisionReferences($compiler))->check($class, $revisions)];
        $failures = array_filter(
            $diagnostics,
            static fn(Diagnostic $diagnostic): bool => $diagnostic->level === DiagnosticLevel::Error
                || $strict && $diagnostic->level === DiagnosticLevel::Warning,
        );

        Assert::assertThat(
            $failures,
            Assert::isEmpty(),
            "The definition of [{$class}] is not valid:\n" . implode("\n", array_map(
                static fn(Diagnostic $diagnostic): string => "- {$diagnostic->describe()}",
                $failures,
            )),
        );
    }

    private static function compiler(): DefinitionCompiler
    {
        return new DefinitionCompiler(Definitions::configuration());
    }
}
