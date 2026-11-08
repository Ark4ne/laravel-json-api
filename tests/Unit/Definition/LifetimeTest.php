<?php

declare(strict_types=1);

namespace Test\Unit\Definition;

use Ark4ne\JsonApi\Definition\Lifetime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Test\app\Revisions\ApiRevision;

final class LifetimeTest extends TestCase
{
    /**
     * @return iterable<string, array{Lifetime, ApiRevision|null, bool}>
     */
    public static function revisions(): iterable
    {
        $always = new Lifetime();
        $until = new Lifetime(until: ApiRevision::Labels);
        $since = new Lifetime(since: ApiRevision::Labels);

        yield 'always, oldest' => [$always, ApiRevision::Initial, true];
        yield 'always, head' => [$always, null, true];
        yield 'until, older' => [$until, ApiRevision::Initial, true];
        yield 'until, bound' => [$until, ApiRevision::Labels, true];
        yield 'until, newer' => [$until, ApiRevision::UsersReplaceTeachers, false];
        yield 'until, head' => [$until, null, false];
        yield 'since, older' => [$since, ApiRevision::Initial, false];
        yield 'since, bound' => [$since, ApiRevision::Labels, true];
        yield 'since, head' => [$since, null, true];
    }

    #[DataProvider('revisions')]
    public function test_a_lifetime_covers_its_bounds_included(Lifetime $lifetime, ?ApiRevision $revision, bool $covers): void
    {
        self::assertSame($covers, $lifetime->covers($revision));
    }
}
