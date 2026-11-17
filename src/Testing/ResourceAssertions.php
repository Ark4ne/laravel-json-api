<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Testing;

use Ark4ne\ApiRevisions\Revisions\Revision;
use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Compilation\ResourceChecker;
use Ark4ne\JsonApi\Definition\DefinitionSource;
use Ark4ne\JsonApi\Definition\ResourceDefinition;
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
     * Fails when the resource has an error at the revision (the head by default); with `$strict`, also on a warning:
     * its definition cannot be compiled, a hook failed, or the check of `ark4ne/laravel-api-revisions`
     * (`Compilation\ResourceChecker`: the history of its fields, the rules of JSON:API) reports it there. The resource
     * must exist at the revision.
     *
     * @param class-string<DefinitionSource> $class
     */
    public static function assertValid(string $class, bool $strict = false, ?Revision $revision = null): void
    {
        // Fails when the resource does not exist at the revision.
        self::definitionAt($class, $revision);

        self::assertNoFailure($class, $strict, self::check($class, $revision ?? Definitions::head()));
    }

    /**
     * `assertValid()` at the head and at every revision the resource exists at.
     *
     * @param class-string<DefinitionSource> $class
     */
    public static function assertValidAtEveryRevision(string $class, bool $strict = false): void
    {
        self::assertNoFailure($class, $strict, self::check($class, null));
    }

    /**
     * The definition of the resource at a revision (the head by default), for snapshot tests: compiled from the
     * current code. Fails when the resource does not exist at it.
     *
     * @param class-string<DefinitionSource> $class
     */
    public static function definitionAt(string $class, ?Revision $revision = null): ResourceDefinition
    {
        try {
            return Definitions::compile($class, $revision);
        } catch (DefinitionException $e) {
            Assert::fail($e->getMessage());
        }
    }

    /**
     * @param class-string<DefinitionSource> $class
     *
     * @return list<Diagnostic>
     */
    private static function check(string $class, ?Revision $only): array
    {
        return (new ResourceChecker(Definitions::compiler()))->check([$class], $only)[$class] ?? [];
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
}
