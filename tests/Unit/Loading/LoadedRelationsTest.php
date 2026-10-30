<?php

declare(strict_types=1);

namespace Test\Unit\Loading;

use Ark4ne\JsonApi\Definition\LoadDefinition;
use Ark4ne\JsonApi\Definition\RelationshipDefinition;
use Ark4ne\JsonApi\Loading\LoadedRelations;
use Illuminate\Database\Eloquent\Collection;
use Test\app\Models\Post;
use Test\app\Models\User;
use Test\TestCase;
use Test\Unit\Loading\Fixtures\DeltaResource;

class LoadedRelationsTest extends TestCase
{
    public function test_a_relationship_read_from_a_relation_is_loaded_when_the_relation_is(): void
    {
        $relationship = RelationshipDefinition::one('author', DeltaResource::class)->source('user');
        $post = new Post();

        self::assertFalse(LoadedRelations::has($post, $relationship));
        self::assertTrue(LoadedRelations::has($post->setRelation('user', null), $relationship));
    }

    public function test_a_relationship_read_from_an_attribute_is_always_loaded(): void
    {
        $relationship = RelationshipDefinition::one('author', DeltaResource::class)->source('title');

        self::assertTrue(LoadedRelations::has(new Post(), $relationship));
    }

    public function test_a_relationship_read_by_a_closure_is_loaded_when_the_roots_of_its_load_are(): void
    {
        $relationship = RelationshipDefinition::one('author', DeltaResource::class)
            ->load(LoadDefinition::of(['user.post', 'comments']))
            ->computed();
        $user = new User();
        $post = (new Post())->setRelation('comments', new Collection());

        self::assertFalse(LoadedRelations::has($post, $relationship));
        self::assertFalse(LoadedRelations::has($post->setRelation('user', $user), $relationship));
        self::assertTrue(LoadedRelations::has($post->setRelation('user', $user->setRelation('post', null)), $relationship));
        self::assertTrue(LoadedRelations::has((new Post())->setRelation('user', null)->setRelation('comments', []), $relationship));
    }

    public function test_a_relationship_read_by_a_closure_without_load_is_always_loaded(): void
    {
        $relationship = RelationshipDefinition::one('author', DeltaResource::class)->computed();

        self::assertTrue(LoadedRelations::has(new Post(), $relationship));
    }

    public function test_what_is_not_an_eloquent_model_is_always_loaded(): void
    {
        $relationship = RelationshipDefinition::one('author', DeltaResource::class)->source('user');

        self::assertTrue(LoadedRelations::has((object) [], $relationship));
        self::assertTrue(LoadedRelations::has(['user' => null], $relationship));
    }
}
