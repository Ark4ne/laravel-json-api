<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Testing;

use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Definition\DefinitionSource;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Support\Diagnostic;
use Ark4ne\JsonApi\Support\DiagnosticLevel;
use PHPUnit\Framework\Assert;

/**
 * Assertions for the test suites of applications: `ResourceAssertions::assertValid(PostResource::class)`.
 *
 * Requires PHPUnit, which the application already has in its development dependencies.
 */
final class ResourceAssertions
{
    /**
     * Fails when the definition of the resource cannot be compiled or has an error; with `$strict`, also on a warning.
     *
     * @param class-string<DefinitionSource> $class
     */
    public static function assertValid(string $class, bool $strict = false): void
    {
        try {
            // Compiled from the current code: a cached or compiled definition may be stale.
            $diagnostics = Definitions::compile($class)->diagnostics;
        } catch (DefinitionException $e) {
            Assert::fail($e->getMessage());
        }

        $failures = array_filter(
            $diagnostics,
            static fn(Diagnostic $diagnostic): bool => $diagnostic->level === DiagnosticLevel::Error
                || $strict && $diagnostic->level === DiagnosticLevel::Warning,
        );

        Assert::assertThat(
            $failures,
            Assert::isEmpty(),
            "The definition of [{$class}] is not valid:\n" . implode("\n", array_map(
                static fn(Diagnostic $diagnostic): string => "- [{$diagnostic->level->value}] {$diagnostic->code}"
                    . ($diagnostic->subject === null ? '' : " ({$diagnostic->subject})")
                    . ": {$diagnostic->message}",
                $failures,
            )),
        );
    }
}
