<?php

declare(strict_types=1);

namespace Test\Unit\Definition;

use Ark4ne\JsonApi\Configuration\Linkage;
use Ark4ne\JsonApi\Definition\Cardinality;
use Ark4ne\JsonApi\Definition\LoadDefinition;
use Ark4ne\JsonApi\Definition\RelationshipDefinition;
use PHPUnit\Framework\TestCase;
use Test\app\Http\Resources\CommentResource;
use Test\app\Http\Resources\NativeCommentResource;
use Test\app\Http\Resources\NativePostResource;
use Test\app\Http\Resources\NativeUserResource;
use Test\app\Http\Resources\PostResource;
use Test\app\Http\Resources\UserResource;
use Test\app\Models\Comment;
use Test\app\Models\Post;

final class RelationshipDefinitionTest extends TestCase
{
    public function test_a_relationship_loads_nothing_and_is_unconditional_by_default(): void
    {
        $relationship = RelationshipDefinition::one('author', UserResource::class);

        self::assertSame('author', $relationship->name);
        self::assertSame([UserResource::class], $relationship->related);
        self::assertSame(Cardinality::One, $relationship->cardinality);
        self::assertTrue($relationship->load->isEmpty());
        self::assertFalse($relationship->conditional);
        self::assertNull($relationship->linkage);
        self::assertFalse($relationship->hasLinks);
        self::assertFalse($relationship->hasMeta);
        self::assertFalse($relationship->hasIdentifierMeta);
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
            ->linkage(Linkage::Included)
            ->hasLinks()
            ->hasMeta()
            ->hasIdentifierMeta()
            ->source('author')
            ->computed();

        self::assertSame('author', $relationship->name);
        self::assertSame('writer', $changed->name);
        self::assertSame(['author'], $changed->load->paths);
        self::assertTrue($changed->conditional);
        self::assertSame(Linkage::Included, $changed->linkage);
        self::assertTrue($changed->hasLinks);
        self::assertTrue($changed->hasMeta);
        self::assertTrue($changed->hasIdentifierMeta);
        self::assertFalse($relationship->hasIdentifierMeta);
        self::assertSame('author', $changed->source);
        self::assertTrue($changed->computed);
        self::assertNull($changed->linkage(null)->linkage);
        self::assertSame(Linkage::Included, $changed->name('other')->linkage);
        self::assertNull($changed->source(null)->source);
    }

    public function test_naming_links_declares_links(): void
    {
        $relationship = RelationshipDefinition::one('author', UserResource::class);

        $named = $relationship->namedLinks('self', 'related');

        self::assertSame([], $relationship->namedLinks);
        self::assertSame(['self', 'related'], $named->namedLinks);
        self::assertTrue($named->hasLinks);
        self::assertFalse($relationship->namedLinks()->hasLinks);
        self::assertTrue($relationship->hasLinks()->namedLinks()->hasLinks);
        self::assertSame([], $named->hasLinks(false)->namedLinks);
        self::assertSame(['self', 'related'], $named->hasLinks()->namedLinks);
    }

    public function test_the_named_links_survive_var_export(): void
    {
        $relationship = RelationshipDefinition::one('author', UserResource::class)->namedLinks('self');

        self::assertEquals($relationship, eval('return ' . var_export($relationship, true) . ';'));
    }

    public function test_a_relationship_to_a_resource_relates_its_definition(): void
    {
        $relationship = RelationshipDefinition::to('author', UserResource::class, Cardinality::Many);

        self::assertEquals(RelationshipDefinition::many('author', UserResource::class), $relationship);
        self::assertFalse($relationship->isNative());
    }

    public function test_a_relationship_to_native_resources_names_their_class_and_relates_no_definition(): void
    {
        $relationship = RelationshipDefinition::to('author', NativeUserResource::class, Cardinality::One);

        self::assertTrue($relationship->isNative());
        self::assertSame(NativeUserResource::class, $relationship->native);
        self::assertSame([], $relationship->related);
        self::assertSame(Cardinality::One, $relationship->cardinality);
        self::assertTrue($relationship->conditional()->isNative());
        self::assertEquals($relationship, eval('return ' . var_export($relationship, true) . ';'));
    }

    public function test_the_definition_of_a_resource_reads_its_relationships_to_native_resources(): void
    {
        $definition = NativePostResource::definition();

        self::assertSame(NativeUserResource::class, $definition->relationship('user')?->native);
        self::assertSame(Cardinality::Many, $definition->relationship('comments')?->cardinality);
        self::assertSame(NativeCommentResource::class, $definition->relationship('notes')?->native);
        self::assertSame(['title', 'user', 'comments', 'writer', 'notes'], $definition->fields());
    }

    public function test_a_polymorphic_relationship_relates_the_resources_of_its_map(): void
    {
        $relationship = RelationshipDefinition::one('commentable', PostResource::class);

        $morph = $relationship->morphs([Post::class => PostResource::class, Comment::class => CommentResource::class]);

        self::assertFalse($relationship->isPolymorphic());
        self::assertTrue($morph->isPolymorphic());
        self::assertSame([PostResource::class, CommentResource::class], $morph->related);
        self::assertSame($relationship, $relationship->morphs([]));
    }
}
