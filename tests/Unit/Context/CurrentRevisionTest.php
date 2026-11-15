<?php

declare(strict_types=1);

namespace Test\Unit\Context;

use Ark4ne\ApiRevisions\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Context\CurrentRevision;
use Test\app\Revisions\ApiRevision;
use Test\app\Revisions\OtherRevision;
use Test\TestCase;

final class CurrentRevisionTest extends TestCase
{
    public function test_an_application_without_revisions_is_at_the_head(): void
    {
        self::assertNull(CurrentRevision::of(Configuration::defaults()));
    }

    public function test_the_head_of_the_enum_is_current_when_nothing_sets_a_revision(): void
    {
        self::assertSame(ApiRevision::UsersReplaceTeachers, CurrentRevision::of(self::configuration()));
    }

    public function test_the_revision_of_the_request_is_current(): void
    {
        self::useRevision(ApiRevision::Labels);

        self::assertSame(ApiRevision::Labels, CurrentRevision::of(self::configuration()));
    }

    public function test_the_innermost_using_is_current_and_ends_with_its_callback(): void
    {
        self::useRevision(ApiRevision::Labels);

        $inside = ApiRevision::using(ApiRevision::Initial, static fn() => CurrentRevision::of(self::configuration()));

        self::assertSame(ApiRevision::Initial, $inside);
        self::assertSame(ApiRevision::Labels, CurrentRevision::of(self::configuration()));
    }

    public function test_a_current_revision_of_another_enum_throws(): void
    {
        self::useRevision(OtherRevision::First);

        $this->expectException(DefinitionException::class);

        CurrentRevision::of(self::configuration());
    }

    private static function configuration(): Configuration
    {
        return Configuration::defaults()->withRevisions(ApiRevision::class);
    }
}
