<?php

declare(strict_types=1);

namespace Test\Unit\Descriptors\Schemas;

use Ark4ne\ApiRevisions\Compilation\Shape;
use Ark4ne\ApiRevisions\Compilation\ShapeSelector;
use Ark4ne\ApiRevisions\Definition\Kind;
use Ark4ne\ApiRevisions\Schema\DeclarationKind;
use Ark4ne\ApiRevisions\Schema\TypeRegistry;
use Ark4ne\JsonApi\Descriptors\Relations\RelationMany;
use Ark4ne\JsonApi\Descriptors\Relations\RelationOne;
use Ark4ne\JsonApi\Descriptors\Relations\RelationRaw;
use Ark4ne\JsonApi\Descriptors\Schemas\RelationshipSchema;
use Ark4ne\JsonApi\Descriptors\Schemas\SchemaTypes;
use PHPUnit\Framework\TestCase;
use Test\app\Http\Resources\CommentResource;
use Test\app\Http\Resources\PostResource;
use Test\app\Http\Resources\UserResource;
use Test\app\Models\Post;
use Test\app\Models\User;
use Test\app\Revisions\ApiRevision;

final class RelationshipSchemaTest extends TestCase
{
    protected function setUp(): void
    {
        SchemaTypes::register();
    }

    public function test_it_holds_the_relation_it_declares(): void
    {
        $relation = new RelationOne(UserResource::class, 'author');

        self::assertSame($relation, (new RelationshipSchema($relation))->relation());
    }

    public function test_the_type_names_the_cardinality_and_the_related_resource(): void
    {
        $one = (new RelationshipSchema(new RelationOne(UserResource::class, 'author')))->toType();
        $many = (new RelationshipSchema(new RelationMany(CommentResource::class, null)))->toType();

        self::assertSame(Kind::Custom, $one->kind);
        self::assertSame(RelationshipSchema::TYPE, $one->name);
        self::assertSame(['type' => 'object', 'x-jsonapi-cardinality' => 'one', 'x-jsonapi-related' => [UserResource::class]], $one->fragment);
        self::assertSame(['type' => 'object', 'x-jsonapi-cardinality' => 'many', 'x-jsonapi-related' => [CommentResource::class]], $many->fragment);
    }

    public function test_the_type_of_a_polymorphic_relationship_names_every_resource_of_its_map(): void
    {
        $morph = (new RelationOne(null, 'subject'))->morphs([User::class => UserResource::class, Post::class => PostResource::class]);

        self::assertSame([PostResource::class, UserResource::class], (new RelationshipSchema($morph))->toType()->fragment['x-jsonapi-related']);
    }

    public function test_the_type_of_a_relationship_guessed_from_the_model_names_no_resource(): void
    {
        self::assertSame([], (new RelationshipSchema(new RelationOne(null, 'author')))->toType()->fragment['x-jsonapi-related']);
    }

    public function test_a_relationship_of_a_resource_is_held_as_a_relation(): void
    {
        $relationship = CommentResource::relationship(static fn(): array => [])->asCollection();
        $schema = new RelationshipSchema($relationship);

        self::assertInstanceOf(RelationRaw::class, $schema->relation());
        self::assertSame($relationship, $schema->relation()->relationship());
        self::assertSame(
            ['type' => 'object', 'x-jsonapi-cardinality' => 'many', 'x-jsonapi-related' => [CommentResource::class]],
            $schema->toType()->fragment,
        );
        self::assertSame('one', (new RelationshipSchema(UserResource::relationship(static fn(): null => null)))->toType()->fragment['x-jsonapi-cardinality']);
    }

    public function test_the_value_of_a_relationship_is_never_refused(): void
    {
        $relationship = new RelationshipSchema(new RelationOne(UserResource::class, 'author'));
        $user = new User();

        self::assertSame($user, $relationship->normalize($user));
        self::assertSame([], $relationship->normalize([]));
    }

    public function test_a_relationship_to_another_resource_at_an_old_revision_needs_no_conversion(): void
    {
        $shape = self::shape(ApiRevision::Initial);
        $field = $shape->field('author');

        self::assertNotNull($field);
        self::assertSame('writer', $field->name);
        self::assertTrue($field->chain->isEmpty());
        self::assertSame([CommentResource::class], $field->type->fragment['x-jsonapi-related']);
        self::assertSame([], $shape->collisions);
    }

    public function test_the_relation_in_force_is_the_one_of_the_revision(): void
    {
        $descriptors = self::descriptors();
        $author = $descriptors['author'];
        $comments = $descriptors['comments'];
        $initial = self::shape(ApiRevision::Initial);
        $head = self::shape(ApiRevision::UsersReplaceTeachers);

        self::assertSame(CommentResource::class, $author->relationAt($initial->field('author') ?? self::fail())->related());
        self::assertSame(UserResource::class, $author->relationAt($head->field('author') ?? self::fail())->related());
        // Renamed only: the head relation.
        self::assertSame($comments->relation(), $comments->relationAt(self::shape(ApiRevision::Labels)->field('comments') ?? self::fail()));
        self::assertNull($initial->field('comments'));
    }

    public function test_the_registry_of_the_base_makes_it(): void
    {
        $relation = new RelationOne(UserResource::class, 'author');
        $made = TypeRegistry::make('relationship', [$relation]);

        self::assertInstanceOf(RelationshipSchema::class, $made);
        self::assertSame($relation, $made->relation());
    }

    /**
     * @return array{author: RelationshipSchema<mixed>, comments: RelationshipSchema<mixed>}
     */
    private static function descriptors(): array
    {
        return [
            'author' => (new RelationshipSchema(new RelationOne(UserResource::class, 'author')))
                ->was(ApiRevision::Initial, name: 'writer', schema: new RelationshipSchema(new RelationOne(CommentResource::class, 'author'))),
            'comments' => (new RelationshipSchema(new RelationMany(CommentResource::class, null)))
                ->since(ApiRevision::Labels)
                ->was(ApiRevision::Labels, name: 'notes'),
        ];
    }

    private static function shape(ApiRevision $revision): Shape
    {
        return ShapeSelector::at(self::descriptors(), $revision, DeclarationKind::Response->direction(), TypeRegistry::converters());
    }
}
