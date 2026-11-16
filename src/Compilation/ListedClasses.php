<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Compilation;

use Ark4ne\ApiRevisions\Checking\ClassFinder;
use Ark4ne\ApiRevisions\Schema\Revisioned;

/**
 * The classes a check of `ark4ne/laravel-api-revisions` runs on, given as a list: the resources `jsonapi:check` and
 * `ResourceAssertions` are asked for.
 *
 * @internal
 */
final readonly class ListedClasses implements ClassFinder
{
    /**
     * @param list<class-string<Revisioned>> $classes
     */
    public function __construct(private array $classes) {}

    public function find(): array
    {
        return $this->classes;
    }
}
