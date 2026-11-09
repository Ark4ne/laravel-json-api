<?php

declare(strict_types=1);

namespace Test\Unit\Definition;

use Ark4ne\JsonApi\Definition\RevisionOrder;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Test\app\Revisions\ApiRevision;
use Test\app\Revisions\OtherRevision;

final class RevisionOrderTest extends TestCase
{
    public function test_revisions_are_ordered_by_the_declaration_of_their_cases(): void
    {
        self::assertLessThan(0, RevisionOrder::compare(ApiRevision::Initial, ApiRevision::Labels));
        self::assertGreaterThan(0, RevisionOrder::compare(ApiRevision::UsersReplaceTeachers, ApiRevision::Labels));
        self::assertSame(0, RevisionOrder::compare(ApiRevision::Labels, ApiRevision::Labels));
    }

    public function test_the_order_ignores_the_values_of_the_cases(): void
    {
        // Declared oldest first: the order of the cases wins, whatever their backing values.
        self::assertLessThan(0, RevisionOrder::compare(ApiRevision::Initial, ApiRevision::UsersReplaceTeachers));
    }

    public function test_comparing_revisions_of_two_enums_throws(): void
    {
        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage(ApiRevision::class);

        RevisionOrder::compare(ApiRevision::Initial, OtherRevision::First);
    }

    /**
     * @return iterable<string, array{ApiRevision|null, ApiRevision, bool, bool}>
     */
    public static function bounds(): iterable
    {
        yield 'older' => [ApiRevision::Initial, ApiRevision::Labels, true, false];
        yield 'same' => [ApiRevision::Labels, ApiRevision::Labels, true, true];
        yield 'newer' => [ApiRevision::UsersReplaceTeachers, ApiRevision::Labels, false, true];
        yield 'head' => [null, ApiRevision::UsersReplaceTeachers, false, true];
    }

    #[DataProvider('bounds')]
    public function test_bounds_are_inclusive_and_the_head_is_newer_than_every_revision(
        ?ApiRevision $requested,
        ApiRevision $bound,
        bool $atMost,
        bool $atLeast,
    ): void {
        self::assertSame($atMost, RevisionOrder::atMost($requested, $bound));
        self::assertSame($atLeast, RevisionOrder::atLeast($requested, $bound));
    }

    public function test_oldest_and_newest_of_several_revisions(): void
    {
        $revisions = [ApiRevision::Labels, ApiRevision::UsersReplaceTeachers, ApiRevision::Initial];

        self::assertSame(ApiRevision::Initial, RevisionOrder::oldest(...$revisions));
        self::assertSame(ApiRevision::UsersReplaceTeachers, RevisionOrder::newest(...$revisions));
    }

    public function test_oldest_and_newest_of_no_revision_are_null(): void
    {
        self::assertNull(RevisionOrder::oldest());
        self::assertNull(RevisionOrder::newest());
    }

    public function test_previous_gives_the_revision_declared_right_before(): void
    {
        self::assertSame(ApiRevision::Labels, RevisionOrder::previous(ApiRevision::UsersReplaceTeachers));
        self::assertSame(ApiRevision::Initial, RevisionOrder::previous(ApiRevision::Labels));
    }

    public function test_previous_of_the_oldest_revision_is_null(): void
    {
        self::assertNull(RevisionOrder::previous(ApiRevision::Initial));
    }
}
