<?php

declare(strict_types=1);

namespace Test\Unit\Testing;

use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\ResourceDefinition;
use Ark4ne\JsonApi\Testing\ResourceAssertions;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use Test\Unit\Compilation\Fixtures\EagerResource;
use Test\Unit\Compilation\Fixtures\FailingHookResource;
use Test\Unit\Compilation\Fixtures\TypedResource;

final class ResourceAssertionsTest extends TestCase
{
    protected function setUp(): void
    {
        Definitions::configure(Configuration::defaults());
    }

    public function test_a_resource_without_diagnostic_is_valid(): void
    {
        ResourceAssertions::assertValid(TypedResource::class, strict: true);

        $this->addToAssertionCount(1);
    }

    public function test_a_resource_with_an_error_is_not_valid_and_the_message_lists_it(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('hook-failure');

        ResourceAssertions::assertValid(FailingHookResource::class);
    }

    public function test_the_current_code_is_asserted_rather_than_a_cached_definition(): void
    {
        Definitions::preload([FailingHookResource::class => ResourceDefinition::of(FailingHookResource::class, 'stale')]);

        $this->expectException(AssertionFailedError::class);

        ResourceAssertions::assertValid(FailingHookResource::class);
    }

    public function test_a_warning_is_tolerated_unless_strict(): void
    {
        ResourceAssertions::assertValid(EagerResource::class);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('eager-attribute');

        ResourceAssertions::assertValid(EagerResource::class, strict: true);
    }
}
