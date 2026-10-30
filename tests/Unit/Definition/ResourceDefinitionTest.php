<?php

declare(strict_types=1);

namespace Test\Unit\Definition;

use Ark4ne\JsonApi\Configuration\Linkage;
use Ark4ne\JsonApi\Definition\AttributeDefinition;
use Ark4ne\JsonApi\Definition\Cardinality;
use Ark4ne\JsonApi\Definition\LoadDefinition;
use Ark4ne\JsonApi\Definition\RelationshipDefinition;
use Ark4ne\JsonApi\Definition\ResourceDefinition;
use Ark4ne\JsonApi\Definition\ValueType;
use Ark4ne\JsonApi\Support\Diagnostic;
use Ark4ne\JsonApi\Support\DiagnosticLevel;
use PHPUnit\Framework\TestCase;
use Test\app\Http\Resources\CommentResource;
use Test\app\Http\Resources\PostResource;
use Test\app\Http\Resources\UserResource;

final class ResourceDefinitionTest extends TestCase
{
    private function definition(): ResourceDefinition
    {
        return ResourceDefinition::of(PostResource::class, 'post')
            ->attributes(
                AttributeDefinition::of('title', ValueType::String),
                AttributeDefinition::of('address', ValueType::Struct)->children(
                    AttributeDefinition::of('city', ValueType::String),
                    AttributeDefinition::of('geo', ValueType::Struct)->children(
                        AttributeDefinition::of('lat', ValueType::Number),
                    ),
                ),
            )
            ->relationships(
                RelationshipDefinition::one('author', UserResource::class)->load(LoadDefinition::of(['author'])),
                RelationshipDefinition::many('comments', CommentResource::class)
                    ->load(LoadDefinition::of(['comments', 'comments.user'], ['comments']))
                    ->linkage(Linkage::Included)
                    ->hasLinks()
                    ->hasMeta(),
            )
            ->links(AttributeDefinition::of('self', ValueType::String)->computed())
            ->meta(AttributeDefinition::of('created-at', ValueType::Date)->format('Y-m-d'))
            ->diagnostics(new Diagnostic(DiagnosticLevel::Warning, 'code', 'Message', 'title'));
    }

    public function test_members_are_indexed_by_name(): void
    {
        $definition = $this->definition();

        self::assertSame(['title', 'address'], array_keys($definition->attributes));
        self::assertSame(['author', 'comments'], array_keys($definition->relationships));
        self::assertSame(['self'], array_keys($definition->links));
        self::assertSame(['created-at'], array_keys($definition->meta));
    }

    public function test_members_are_read_by_name(): void
    {
        $definition = $this->definition();

        self::assertSame(ValueType::String, $definition->attribute('title')?->type);
        self::assertNull($definition->attribute('missing'));
        self::assertSame(Cardinality::Many, $definition->relationship('comments')?->cardinality);
        self::assertNull($definition->relationship('missing'));
        self::assertTrue($definition->hasRelationship('author'));
        self::assertFalse($definition->hasRelationship('title'));
    }

    public function test_fields_list_attribute_paths_then_relationships(): void
    {
        self::assertSame(
            ['title', 'address', 'address.city', 'address.geo', 'address.geo.lat', 'author', 'comments'],
            $this->definition()->fields(),
        );
    }

    public function test_setters_return_a_new_definition(): void
    {
        $empty = ResourceDefinition::of(PostResource::class, 'post');

        $filled = $empty->attributes(AttributeDefinition::of('title', ValueType::String));

        self::assertNotSame($empty, $filled);
        self::assertSame([], $empty->attributes);
    }

    public function test_the_definition_survives_var_export(): void
    {
        $definition = $this->definition();

        self::assertEquals($definition, eval('return ' . var_export($definition, true) . ';'));
    }

    public function test_the_definition_survives_serialize(): void
    {
        $definition = $this->definition();

        self::assertEquals($definition, unserialize(serialize($definition)));
    }
}
