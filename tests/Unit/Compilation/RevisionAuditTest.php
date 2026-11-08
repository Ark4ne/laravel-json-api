<?php

declare(strict_types=1);

namespace Test\Unit\Compilation;

use Ark4ne\JsonApi\Compilation\DefinitionCompiler;
use Ark4ne\JsonApi\Compilation\RevisionAudit;
use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Support\DiagnosticLevel;
use PHPUnit\Framework\TestCase;
use Test\app\Http\Resources\UserResource;
use Test\app\Revisions\ApiRevision;
use Test\app\Revisions\MemberResource;
use Test\app\Revisions\ProfileResource;
use Test\app\Revisions\TeacherResource;
use Test\Unit\Compilation\Fixtures\EmptyLifetimeResource;
use Test\Unit\Compilation\References\BoundedReferencesResource;
use Test\Unit\Compilation\References\ForgottenWasResource;
use Test\Unit\Compilation\References\StaleHeadResource;

final class RevisionAuditTest extends TestCase
{
    private RevisionAudit $audit;

    protected function setUp(): void
    {
        $this->audit = new RevisionAudit(new DefinitionCompiler(Configuration::defaults()));
    }

    public function test_a_relationship_to_a_resource_since_a_revision_without_was_is_reported_at_the_older_ones(): void
    {
        $diagnostics = $this->audit->check(ForgottenWasResource::class);

        self::assertCount(1, $diagnostics);
        self::assertSame(DiagnosticLevel::Error, $diagnostics[0]->level);
        self::assertSame(DefinitionCompiler::REVISION_UNAVAILABLE_RESOURCE, $diagnostics[0]->code);
        self::assertSame('members', $diagnostics[0]->subject);
        self::assertStringContainsString(MemberResource::class, $diagnostics[0]->message);
        self::assertStringContainsString('[Initial, Labels]', $diagnostics[0]->message);
    }

    public function test_a_head_relationship_to_a_resource_until_a_revision_is_reported(): void
    {
        $diagnostics = $this->audit->check(StaleHeadResource::class);

        self::assertCount(1, $diagnostics);
        self::assertSame('teacher', $diagnostics[0]->subject);
        self::assertStringContainsString(TeacherResource::class, $diagnostics[0]->message);
        self::assertStringContainsString('[head, UsersReplaceTeachers]', $diagnostics[0]->message);
    }

    public function test_relationships_bounded_to_the_life_of_their_resources_are_not_reported(): void
    {
        self::assertSame([], $this->audit->check(BoundedReferencesResource::class));
        self::assertSame([], $this->audit->check(ProfileResource::class));
        self::assertSame([], $this->audit->check(UserResource::class));
    }

    public function test_the_check_may_be_restricted_to_some_revisions(): void
    {
        self::assertSame([], $this->audit->check(ForgottenWasResource::class, [null, ApiRevision::UsersReplaceTeachers]));
        self::assertStringContainsString(
            'at [Labels]',
            $this->audit->check(ForgottenWasResource::class, [ApiRevision::Labels])[0]->message,
        );
        self::assertSame([], $this->audit->check(StaleHeadResource::class, [ApiRevision::Labels]));
        self::assertCount(1, $this->audit->check(StaleHeadResource::class, [null]));
    }

    public function test_the_revisions_checked_are_those_of_the_resource_and_of_the_resources_it_relates_to(): void
    {
        self::assertSame([null], $this->audit->revisions(UserResource::class));
        self::assertSame([null, ...ApiRevision::cases()], $this->audit->revisions(ForgottenWasResource::class));
        self::assertSame([null, ...ApiRevision::cases()], $this->audit->revisions(ProfileResource::class));
    }

    public function test_a_resource_that_cannot_be_compiled_has_no_reference_reported(): void
    {
        self::assertSame([], $this->audit->check(EmptyLifetimeResource::class));
        self::assertSame([null], $this->audit->revisions(EmptyLifetimeResource::class));
    }
}
