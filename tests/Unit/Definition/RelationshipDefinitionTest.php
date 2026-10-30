<?php

declare(strict_types=1);

namespace Test\Unit\Definition;

use Ark4ne\JsonApi\Definition\Cardinality;
use Ark4ne\JsonApi\Definition\LoadDefinition;
use Ark4ne\JsonApi\Definition\RelationshipDefinition;
use PHPUnit\Framework\TestCase;
use Test\app\Http\Resources\UserResource;

final class RelationshipDefinitionTest extends TestCase
{
    public function test_a_relationship_loads_nothing_and_is_unconditional_by_default(): void
    {
        $relationship = RelationshipDefinition::one('author', UserResource::class);

        self::assertSame('author', $relationship->name);
        self::assertSame(UserResource::class, $relationship->related);
        self::assertSame(Cardinality::One, $relationship->cardinality);
        self::assertTrue($relationship->load->isEmpty());
        self::assertFalse($relationship->conditional);
        self::assertNull($relationship->whenIncluded);
        self::assertFalse($relationship->hasLinks);
        self::assertFalse($relationship->hasMeta);
        self::assertNull($relationship->source);
        self::assertFalse($relationship->computed);
        self::assertSame(Cardinality::Many, RelationshipDefinition::many('posts', UserResource::class)->cardinality);
    }

    public function test_withers_return_a_new_relationship(): void
    {
        $relationship = RelationshipDefinition::one('author', UserResource::class);

        $changed = $relationship
            ->name('writer')
            ->load(LoadDefinition::of(['author']))
            ->conditional()
            ->whenIncluded(true)
            ->hasLinks()
            ->hasMeta()
            ->source('author')
            ->computed();

        self::assertSame('author', $relationship->name);
        self::assertSame('writer', $changed->name);
        self::assertSame(['author'], $changed->load->paths);
        self::assertTrue($changed->conditional);
        self::assertTrue($changed->whenIncluded);
        self::assertTrue($changed->hasLinks);
        self::assertTrue($changed->hasMeta);
        self::assertSame('author', $changed->source);
        self::assertTrue($changed->computed);
        self::assertNull($changed->whenIncluded(null)->whenIncluded);
        self::assertNull($changed->source(null)->source);
    }
}
