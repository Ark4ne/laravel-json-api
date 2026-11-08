<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Testing;

use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Compilation\RevisionAudit;
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
 * Every definition is compiled from the current code, once per assertion: a cached or compiled definition may be
 * stale. Requires PHPUnit, which the application already has in its development dependencies.
 */
final class ResourceAssertions
{
    /**
     * Fails when the definition of the resource cannot be compiled or has an error; with `$strict`, also on a warning.
     * The relationships must reach resources existing at the revision (`revision-unavailable-resource`).
     *
     * At the head (`null`), the diagnostics are those of the head definition, which carries those of every revision,
     * as `jsonapi:check` prints them; at a revision, those of its shape and of the revision declarations
     * (`RevisionAudit::diagnosticsAt()`), and the resource must exist at it.
     *
     * @param class-string<DefinitionSource> $class
     */
    public static function assertValid(string $class, bool $strict = false, ?Revision $revision = null): void
    {
        $audit = self::audit();

        try {
            $diagnostics = $revision === null
                ? $audit->shapes($class)[0]->diagnostics
                : $audit->diagnosticsAt($class, $revision) ?? throw self::notInRevision($class, $revision);
        } catch (DefinitionException $e) {
            Assert::fail($e->getMessage());
        }

        self::assertNoFailure($class, $strict, [...$diagnostics, ...$audit->check($class, [$revision])]);
    }

    /**
     * `assertValid()` at every revision: the diagnostics of every shape, and the references between resources at the
     * head and at every case of the enums the resource and its related resources declare with.
     *
     * @param class-string<DefinitionSource> $class
     */
    public static function assertValidAtEveryRevision(string $class, bool $strict = false): void
    {
        $audit = self::audit();

        try {
            $diagnostics = $audit->shapes($class)[0]->diagnostics;
        } catch (DefinitionException $e) {
            Assert::fail($e->getMessage());
        }

        self::assertNoFailure($class, $strict, [...$diagnostics, ...$audit->check($class)]);
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
            return self::audit()->shapeAt($class, $revision) ?? throw self::notInRevision($class, $revision);
        } catch (DefinitionException $e) {
            Assert::fail($e->getMessage());
        }
    }

    /**
     * @param list<Diagnostic> $diagnostics
     */
    private static function assertNoFailure(string $class, bool $strict, array $diagnostics): void
    {
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

    private static function notInRevision(string $class, Revision $revision): DefinitionException
    {
        return DefinitionException::notInRevision($class, $revision);
    }

    private static function audit(): RevisionAudit
    {
        return new RevisionAudit(Definitions::compiler());
    }
}
