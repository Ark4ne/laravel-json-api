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
use Test\Unit\Compilation\Fixtures\EagerResource;
use Test\Unit\Compilation\Fixtures\FailingHookResource;
use Test\Unit\Compilation\Fixtures\TypedResource;
use Test\Unit\Compilation\References\BoundedReferencesResource;
use Test\Unit\Compilation\References\ForgottenWasResource;
use Test\Unit\Compilation\References\HeadSharedErrorResource;
use Test\Unit\Compilation\References\InvalidAtRevisionResource;
use Test\Unit\Compilation\References\OlderSharedErrorResource;

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

    public function test_a_resource_is_asserted_at_a_revision(): void
    {
        ResourceAssertions::assertValid(InvalidAtRevisionResource::class, revision: ApiRevision::Labels);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('[error] [Initial] invalid-member (author)');

        ResourceAssertions::assertValid(InvalidAtRevisionResource::class, revision: ApiRevision::Initial);
    }

    public function test_the_references_are_asserted_at_the_revision(): void
    {
        ResourceAssertions::assertValid(ForgottenWasResource::class);
        ResourceAssertions::assertValid(ForgottenWasResource::class, revision: ApiRevision::UsersReplaceTeachers);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('revision-unavailable-resource (members)');

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
        $this->expectExceptionMessage('does not exist at [Initial, Labels]');

        ResourceAssertions::assertValidAtEveryRevision(ForgottenWasResource::class);
    }

    public function test_every_revision_is_asserted_with_the_diagnostics_of_every_shape(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('[Initial] invalid-member');

        ResourceAssertions::assertValidAtEveryRevision(InvalidAtRevisionResource::class);
    }

    public function test_the_definition_at_a_revision_is_compiled_from_the_current_code(): void
    {
        Definitions::preload([ProfileResource::class => ResourceDefinition::of(ProfileResource::class, 'stale')]);

        $definition = ResourceAssertions::definitionAt(ProfileResource::class, ApiRevision::Initial);

        self::assertSame('profiles', $definition->type);
        self::assertSame(ApiRevision::Initial, $definition->revision);
        self::assertArrayHasKey('name', $definition->attributes);
    }

    public function test_the_definition_at_a_revision_the_resource_is_absent_from_fails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage(MemberResource::class);

        ResourceAssertions::definitionAt(MemberResource::class, ApiRevision::Labels);
    }

    public function test_a_revision_is_asserted_with_the_errors_its_shape_shares_with_the_head(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('invalid-member (author)');

        ResourceAssertions::assertValid(HeadSharedErrorResource::class, revision: ApiRevision::Initial);
    }

    public function test_a_revision_is_asserted_with_the_errors_its_shape_shares_with_an_older_one(): void
    {
        ResourceAssertions::assertValid(OlderSharedErrorResource::class, revision: ApiRevision::UsersReplaceTeachers);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('invalid-member (writer)');

        ResourceAssertions::assertValid(OlderSharedErrorResource::class, revision: ApiRevision::Labels);
    }

    public function test_a_resource_is_compiled_once_by_an_assertion(): void
    {
        ProfileResource::$reads = 0;

        ResourceAssertions::assertValidAtEveryRevision(ProfileResource::class);

        self::assertSame(1, ProfileResource::$reads);
    }
}
