<?php

declare(strict_types=1);

namespace Test\Unit\Laravel;

use Ark4ne\JsonApi\Laravel\ResourceFinder;
use PHPUnit\Framework\TestCase;
use Test\Unit\Laravel\Fixtures\FoundResource;
use Test\Unit\Laravel\Fixtures\Nested\NestedResource;

final class ResourceFinderTest extends TestCase
{
    public function test_concrete_resources_are_found_recursively_and_sorted(): void
    {
        self::assertSame(
            [FoundResource::class, NestedResource::class],
            (new ResourceFinder([__DIR__ . '/Fixtures']))->find(),
        );
    }

    public function test_abstract_classes_collections_other_classes_and_files_without_class_are_skipped(): void
    {
        self::assertNotContains(
            'Test\Unit\Laravel\Fixtures\AbstractResource',
            (new ResourceFinder([__DIR__ . '/Fixtures']))->find(),
        );
    }

    public function test_a_class_that_cannot_be_loaded_is_skipped(): void
    {
        self::assertNotContains(
            'Test\Unit\Laravel\Fixtures\BrokenResource',
            (new ResourceFinder([__DIR__ . '/Fixtures']))->find(),
        );
    }

    public function test_a_resource_reached_through_two_paths_is_found_once(): void
    {
        self::assertSame(
            [FoundResource::class, NestedResource::class],
            (new ResourceFinder([__DIR__ . '/Fixtures', __DIR__ . '/Fixtures/Nested']))->find(),
        );
    }

    public function test_a_missing_directory_finds_nothing(): void
    {
        self::assertSame([], (new ResourceFinder([__DIR__ . '/Missing']))->find());
    }
}
