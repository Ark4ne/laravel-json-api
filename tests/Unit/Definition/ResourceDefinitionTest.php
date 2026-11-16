<?php

declare(strict_types=1);

namespace Test\Unit\Definition;

use Ark4ne\ApiRevisions\Compilation\Shape;
use Ark4ne\ApiRevisions\Compilation\ShapeSelector;
use Ark4ne\ApiRevisions\Definition\Kind;
use Ark4ne\ApiRevisions\Schema\DeclarationKind;
use Ark4ne\ApiRevisions\Schema\Descriptor;
use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\JsonApi\Configuration\Linkage;
use Ark4ne\JsonApi\Definition\Cardinality;
use Ark4ne\JsonApi\Definition\Lifetime;
use Ark4ne\JsonApi\Definition\LoadDefinition;
use Ark4ne\JsonApi\Definition\RelationshipDefinition;
use Ark4ne\JsonApi\Definition\ResourceDefinition;
use Ark4ne\JsonApi\Support\Diagnostic;
use Ark4ne\JsonApi\Support\DiagnosticLevel;
use PHPUnit\Framework\TestCase;
use Test\app\Http\Resources\CommentResource;
use Test\app\Http\Resources\PostResource;
use Test\app\Http\Resources\UserResource;
use Test\app\Revisions\ApiRevision;

final class ResourceDefinitionTest extends TestCase
{
    /**
     * @param array<string, Descriptor<mixed>> $descriptors
     */
    private static function shape(array $descriptors, ApiRevision $revision = ApiRevision::UsersReplaceTeachers): Shape
    {
        return ShapeSelector::at($descriptors, $revision, DeclarationKind::Response->direction());
    }

    private static function attributes(ApiRevision $revision = ApiRevision::UsersReplaceTeachers): Shape
    {
        return self::shape([
            'title' => Schema::string()->was(ApiRevision::Initial, name: 'name'),
            'address' => Schema::object([
                'city' => Schema::string(),
                'geo' => Schema::object(['lat' => Schema::float()]),
            ]),
        ], $revision);
    }

    private function definition(): ResourceDefinition
    {
        return ResourceDefinition::of(PostResource::class, 'post')
            ->values(
                self::attributes(),
                self::shape(['created-at' => Schema::date()]),
                self::shape(['version' => Schema::string()]),
            )
            ->relationships(
                RelationshipDefinition::one('author', UserResource::class)->load(LoadDefinition::of(['author'])),
                RelationshipDefinition::many('comments', CommentResource::class)
                    ->load(LoadDefinition::of(['comments', 'comments.user'], ['comments']))
                    ->linkage(Linkage::Included)
                    ->hasLinks()
                    ->hasMeta(),
            )
            ->links('self')
            ->diagnostics(new Diagnostic(DiagnosticLevel::Warning, 'code', 'Message', 'title'));
    }

    public function test_members_are_indexed_by_name(): void
    {
        $definition = $this->definition();

        self::assertSame(['title', 'address'], $definition->attributes?->names());
        self::assertSame(['author', 'comments'], array_keys($definition->relationships));
        self::assertSame(['self'], $definition->links);
        self::assertSame(['created-at'], $definition->meta?->names());
        self::assertSame(['version'], $definition->documentMeta?->names());
    }

    public function test_members_are_read_by_name(): void
    {
        $definition = $this->definition();

        self::assertSame(Kind::String, $definition->attribute('title')?->type->kind);
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

    public function test_an_attribute_is_read_by_its_name_at_the_revision(): void
    {
        $definition = ResourceDefinition::of(PostResource::class, 'post')
            ->values(self::attributes(ApiRevision::Initial), null, null)
            ->revision(ApiRevision::Initial);

        self::assertNull($definition->attribute('title'));
        self::assertSame('title', $definition->attribute('name')?->headKey);
        self::assertSame(['name', 'address', 'address.city', 'address.geo', 'address.geo.lat'], $definition->fields());
    }

    public function test_the_sparse_attributes_are_named(): void
    {
        $definition = $this->definition()->sparse('title');

        self::assertSame(['title'], $definition->sparse);
        self::assertTrue($definition->isSparse('title'));
        self::assertFalse($definition->isSparse('address'));
        self::assertSame([], $this->definition()->sparse);
    }

    public function test_a_definition_without_values_has_no_attribute(): void
    {
        $definition = $this->definition()->values(null, null, null);

        self::assertNull($definition->attributes);
        self::assertNull($definition->meta);
        self::assertNull($definition->documentMeta);
        self::assertNull($definition->attribute('title'));
        self::assertSame(['author', 'comments'], $definition->fields());
    }

    public function test_setters_return_a_new_definition(): void
    {
        $empty = ResourceDefinition::of(PostResource::class, 'post');

        $filled = $empty->values(self::attributes(), null, null);

        self::assertNotSame($empty, $filled);
        self::assertNull($empty->attributes);
    }

    public function test_the_definition_without_its_values_survives_var_export(): void
    {
        $definition = $this->definition()->values(null, null, null);

        self::assertEquals($definition, eval('return ' . var_export($definition, true) . ';'));
    }

    public function test_the_definition_survives_serialize(): void
    {
        $definition = $this->definition();

        self::assertEquals($definition, unserialize(serialize($definition)));
    }

    public function test_the_definition_names_its_revision(): void
    {
        $definition = $this->definition();
        $revised = $definition->revision(ApiRevision::Labels);

        self::assertNull($definition->revision);
        self::assertSame(ApiRevision::Labels, $revised->revision);
        self::assertNull($revised->revision(null)->revision);
        self::assertEquals($definition->attributes, $revised->attributes);
    }

    public function test_the_definition_names_the_lifetime_of_the_resource(): void
    {
        $definition = $this->definition()->lifetime(new Lifetime(ApiRevision::Initial, ApiRevision::Labels));

        self::assertSame(ApiRevision::Initial, $definition->since);
        self::assertSame(ApiRevision::Labels, $definition->until);
    }

    public function test_the_revision_is_kept_by_the_other_replacements(): void
    {
        $definition = $this->definition()
            ->revision(ApiRevision::Labels)
            ->lifetime(new Lifetime(until: ApiRevision::Labels))
            ->values(null, null, null);

        self::assertSame(ApiRevision::Labels, $definition->revision);
        self::assertSame(ApiRevision::Labels, $definition->until);
    }

    public function test_a_definition_with_revisions_survives_var_export(): void
    {
        $definition = $this->definition()
            ->values(null, null, null)
            ->revision(ApiRevision::Initial)
            ->lifetime(new Lifetime(ApiRevision::Initial, ApiRevision::Labels))
            ->fieldNames(['display_name' => 'name'])
            ->sparse('name');

        self::assertEquals($definition, eval('return ' . var_export($definition, true) . ';'));
        self::assertEquals($definition, unserialize(serialize($definition)));
    }

    public function test_the_field_names_are_kept_by_the_other_replacements(): void
    {
        $definition = $this->definition()->fieldNames(['display_name' => 'label', 'email' => 'email'])->revision(ApiRevision::Labels);

        self::assertSame(['display_name' => 'label', 'email' => 'email'], $definition->fieldNames);
    }
}
