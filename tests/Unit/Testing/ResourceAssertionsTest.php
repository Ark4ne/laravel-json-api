<?php

declare(strict_types=1);

namespace Test\Unit\Testing;

use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\ResourceDefinition;
use Ark4ne\JsonApi\Testing\ResourceAssertions;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use Test\app\Revisions\ApiRevision;
use Test\app\Revisions\MemberResource;
use Test\app\Revisions\ProfileResource;
use Test\Unit\Compilation\Fixtures\FailingHookResource;
use Test\Unit\Compilation\Fixtures\TypedResource;
use Test\Unit\Compilation\References\BoundedReferencesResource;
use Test\Unit\Compilation\References\ForgottenWasResource;

final class ResourceAssertionsTest extends TestCase
{
    protected function setUp(): void
    {
        Definitions::configure(Configuration::defaults()->withRevisions(ApiRevision::class));
    }

    protected function tearDown(): void
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
        $this->expectExceptionMessage('[error] definition-error: Needs a real model.');

        ResourceAssertions::assertValid(FailingHookResource::class);
    }

    public function test_the_current_code_is_asserted_rather_than_a_cached_definition(): void
    {
        Definitions::preload([FailingHookResource::class => ResourceDefinition::of(FailingHookResource::class, 'stale')]);

        $this->expectException(AssertionFailedError::class);

        ResourceAssertions::assertValid(FailingHookResource::class);
    }

    public function test_the_references_are_asserted_at_the_revision(): void
    {
        ResourceAssertions::assertValid(ForgottenWasResource::class);
        ResourceAssertions::assertValid(ForgottenWasResource::class, revision: ApiRevision::UsersReplaceTeachers);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('[error] [Labels] jsonapi-absent-resource (relationships.members)');

        ResourceAssertions::assertValid(ForgottenWasResource::class, revision: ApiRevision::Labels);
    }

    public function test_a_resource_absent_from_the_revision_is_not_valid_at_it(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('does not exist at the revision');

        ResourceAssertions::assertValid(MemberResource::class, revision: ApiRevision::Initial);
    }

    public function test_a_resource_is_asserted_at_every_revision(): void
    {
        ResourceAssertions::assertValidAtEveryRevision(BoundedReferencesResource::class, strict: true);
        ResourceAssertions::assertValidAtEveryRevision(ProfileResource::class, strict: true);
        ResourceAssertions::assertValidAtEveryRevision(TypedResource::class, strict: true);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('[error] [Initial] jsonapi-absent-resource (relationships.members)');

        ResourceAssertions::assertValidAtEveryRevision(ForgottenWasResource::class);
    }

    public function test_the_definition_at_a_revision_is_compiled_from_the_current_code(): void
    {
        Definitions::preload([ProfileResource::class => ResourceDefinition::of(ProfileResource::class, 'stale')]);

        $definition = ResourceAssertions::definitionAt(ProfileResource::class, ApiRevision::Initial);

        self::assertSame('profiles', $definition->type);
        self::assertSame(ApiRevision::Initial, $definition->revision);
        self::assertNotNull($definition->attribute('name'));
        self::assertSame('profiles', ResourceAssertions::definitionAt(ProfileResource::class)->type);
    }

    public function test_the_definition_at_a_revision_the_resource_is_absent_from_fails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage(MemberResource::class);

        ResourceAssertions::definitionAt(MemberResource::class, ApiRevision::Labels);
    }
}
