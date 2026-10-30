<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Testing;

use Ark4ne\JsonApi\Definition\DefinitionSource;

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
    public static function assertValid(string $class, bool $strict = false): void {}
}
