<?php

declare(strict_types=1);

namespace Test\Unit\Rendering;

use Ark4ne\JsonApi\Definition\Cardinality;
use Ark4ne\JsonApi\Rendering\ResolvedRelationship;
use PHPUnit\Framework\TestCase;
use Test\Unit\Rendering\Fixtures\FakeResource;

final class ResolvedRelationshipTest extends TestCase
{
    public function test_the_related_resources_are_listed_whatever_the_cardinality(): void
    {
        $user = new FakeResource('user', 1);

        self::assertSame([$user], ResolvedRelationship::toOne($user)->resources());
        self::assertSame([], ResolvedRelationship::toOne(null)->resources());
        self::assertSame([$user, $user], ResolvedRelationship::toMany([$user, $user])->resources());
        self::assertSame([], ResolvedRelationship::withoutData(Cardinality::Many)->resources());
    }

    public function test_the_identifier_meta_follow_the_related_resources(): void
    {
        $user = new FakeResource('user', 1);

        self::assertSame([['since' => 2020]], ResolvedRelationship::toOne($user, identifierMeta: ['since' => 2020])->identifierMeta);
        self::assertSame([[]], ResolvedRelationship::toOne($user)->identifierMeta);
        self::assertSame([], ResolvedRelationship::toOne(null, identifierMeta: ['since' => 2020])->identifierMeta);
        self::assertSame([], ResolvedRelationship::withoutData(Cardinality::One)->identifierMeta);
    }

    public function test_a_relationship_without_data_says_so(): void
    {
        $relationship = ResolvedRelationship::withoutData(Cardinality::One, ['related' => '/boss']);

        self::assertFalse($relationship->hasData);
        self::assertSame(['related' => '/boss'], $relationship->links);
        self::assertTrue(ResolvedRelationship::toOne(null)->hasData);
    }
}
