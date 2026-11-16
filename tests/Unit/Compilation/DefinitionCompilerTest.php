<?php

declare(strict_types=1);

namespace Test\Unit\Compilation;

use Ark4ne\ApiRevisions\Compilation\FieldDefinition;
use Ark4ne\ApiRevisions\Definition\Kind;
use Ark4ne\JsonApi\Compilation\DefinitionCompiler;
use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Configuration\Linkage;
use Ark4ne\JsonApi\Definition\Cardinality;
use Ark4ne\JsonApi\Definition\ResourceDefinition;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\JsonApi\Support\Diagnostic;
use Ark4ne\JsonApi\Support\DiagnosticLevel;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use stdClass;
use Test\app\Enums\State;
use Test\app\Http\Resources\CommentResource;
use Test\app\Http\Resources\UserResource;
use Test\app\Revisions\ApiRevision;
use Test\app\Revisions\MemberResource;
use Test\app\Revisions\OtherRevision;
use Test\app\Revisions\ProfileResource;
use Test\app\Revisions\TeacherChildResource;
use Test\app\Revisions\TeacherResource;
use Test\Unit\Compilation\Fixtures\AttributeTypedResource;
use Test\Unit\Compilation\Fixtures\BlogPostResource;
use Test\Unit\Compilation\Fixtures\EmptyLifetimeResource;
use Test\Unit\Compilation\Fixtures\FailingHookResource;
use Test\Unit\Compilation\Fixtures\OverriddenTypeResource;
use Test\Unit\Compilation\Fixtures\PrefixedTypeNaming;
use Test\Unit\Compilation\Fixtures\TypedResource;

final class DefinitionCompilerTest extends TestCase
{
    private function compile(string $class, ?Configuration $configuration = null, ?ApiRevision $revision = null): ResourceDefinition
    {
        /** @var class-string<\Ark4ne\JsonApi\Definition\DefinitionSource> $class */
        return (new DefinitionCompiler($configuration ?? Configuration::defaults()))->compile($class, $revision);
    }

    private static function revised(): Configuration
    {
        return Configuration::defaults()->withRevisions(ApiRevision::class);
    }

    private function attribute(ResourceDefinition $definition, string $name): FieldDefinition
    {
        $attribute = $definition->attribute($name);
        self::assertNotNull($attribute, "Attribute [$name] is missing.");

        return $attribute;
    }

    /**
     * @return list<string>
     */
    private function codes(ResourceDefinition $definition): array
    {
        return array_map(static fn(Diagnostic $diagnostic): string => $diagnostic->code, $definition->diagnostics);
    }

    public function test_a_resource_without_type_gets_that_of_the_naming_convention(): void
    {
        self::assertSame('blog-post', $this->compile(BlogPostResource::class)->type);
        self::assertSame(
            'blog_posts',
            $this->compile(BlogPostResource::class, Configuration::fromArray(['type' => ['naming' => 'snake-plural']]))->type,
        );
        self::assertSame(
            'app.blogpostresource',
            $this->compile(BlogPostResource::class, Configuration::fromArray(['type' => ['naming' => PrefixedTypeNaming::class]]))->type,
        );
    }

    public function test_the_type_attribute_wins_over_the_naming_convention(): void
    {
        self::assertSame('articles', $this->compile(AttributeTypedResource::class)->type);
    }

    public function test_the_type_hook_wins_over_the_type_attribute(): void
    {
        self::assertSame('stories', $this->compile(OverriddenTypeResource::class)->type);
    }

    public function test_the_type_is_read_from_the_type_hook(): void
    {
        $definition = $this->compile(TypedResource::class);

        self::assertSame(TypedResource::class, $definition->class);
        self::assertSame('typed', $definition->type);
    }

    public function test_attributes_are_listed_in_declaration_order(): void
    {
        self::assertSame(
            ['title', 'name', 'count', 'ratio', 'active', 'published-at', 'state', 'tags', 'full-name', 'secret', 'content', 'address', 'grouped'],
            $this->compile(TypedResource::class)->attributes?->names(),
        );
    }

    public function test_each_value_gives_the_type_of_its_shape(): void
    {
        $definition = $this->compile(TypedResource::class);

        self::assertSame(Kind::String, $this->attribute($definition, 'title')->type->kind);
        self::assertSame(Kind::Int, $this->attribute($definition, 'count')->type->kind);
        self::assertSame(Kind::Float, $this->attribute($definition, 'ratio')->type->kind);
        self::assertSame(Kind::Bool, $this->attribute($definition, 'active')->type->kind);
        self::assertSame(Kind::Date, $this->attribute($definition, 'published-at')->type->kind);
        self::assertSame(Kind::Enum, $this->attribute($definition, 'state')->type->kind);
        self::assertSame(State::class, $this->attribute($definition, 'state')->type->enum);
        self::assertSame(Kind::List, $this->attribute($definition, 'tags')->type->kind);
        self::assertSame(Kind::String, $this->attribute($definition, 'tags')->type->items?->kind);
        self::assertSame(Kind::Object, $this->attribute($definition, 'address')->type->kind);
    }

    public function test_nullable_defaults_to_the_configuration_unless_the_descriptor_says_otherwise(): void
    {
        $definition = $this->compile(TypedResource::class);

        self::assertTrue($this->attribute($definition, 'title')->nullable);
        self::assertFalse($this->attribute($definition, 'name')->nullable);
    }

    public function test_a_struct_records_its_members_recursively(): void
    {
        $address = $this->attribute($this->compile(TypedResource::class), 'address');

        self::assertSame(['city', 'geo'], $address->children?->names());
        self::assertSame(Kind::Object, $address->children->field('geo')?->type->kind);
        self::assertSame(Kind::Float, $address->children->field('geo')->children?->field('lat')?->type->kind);
    }

    public function test_conditions_mark_an_attribute_conditional(): void
    {
        $definition = $this->compile(TypedResource::class);

        self::assertTrue($this->attribute($definition, 'secret')->conditional);
        self::assertFalse($this->attribute($definition, 'title')->conditional);
    }

    public function test_when_in_fields_marks_an_attribute_conditional_and_sparse(): void
    {
        $definition = $this->compile(TypedResource::class);

        self::assertTrue($this->attribute($definition, 'content')->conditional);
        self::assertSame(['content'], $definition->sparse);
        self::assertTrue($definition->isSparse('content'));
        self::assertFalse($definition->isSparse('title'));
    }

    public function test_the_members_of_apply_when_are_kept_and_conditional(): void
    {
        $grouped = $this->attribute($this->compile(TypedResource::class), 'grouped');

        self::assertSame(Kind::Int, $grouped->type->kind);
        self::assertTrue($grouped->conditional);
    }

    public function test_relationships_record_the_related_resource_and_the_cardinality(): void
    {
        $definition = $this->compile(TypedResource::class);

        self::assertSame(['author', 'comments', 'reviewer'], array_keys($definition->relationships));
        self::assertSame([UserResource::class], $definition->relationships['author']->related);
        self::assertSame(Cardinality::One, $definition->relationships['author']->cardinality);
        self::assertSame([CommentResource::class], $definition->relationships['comments']->related);
        self::assertSame(Cardinality::Many, $definition->relationships['comments']->cardinality);
    }

    public function test_relationships_record_their_eager_loads_without_closures(): void
    {
        $definition = $this->compile(TypedResource::class);

        self::assertSame(['author'], $definition->relationships['author']->load->paths);
        self::assertSame([], $definition->relationships['author']->load->constrained);
        self::assertSame(['comments'], $definition->relationships['comments']->load->paths);
        self::assertSame(['comments'], $definition->relationships['comments']->load->constrained);
        self::assertTrue($definition->relationships['reviewer']->load->isEmpty());
    }

    public function test_relationships_record_links_meta_and_conditions(): void
    {
        $definition = $this->compile(TypedResource::class);
        $author = $definition->relationships['author'];
        $comments = $definition->relationships['comments'];
        $reviewer = $definition->relationships['reviewer'];

        self::assertFalse($author->hasLinks);
        self::assertTrue($comments->hasLinks);
        self::assertTrue($comments->hasMeta);
        self::assertSame(Linkage::Included, $comments->linkage);
        self::assertNull($author->linkage);
        self::assertTrue($reviewer->conditional);
        self::assertTrue($reviewer->computed);
        self::assertSame('author', $author->source);
    }

    public function test_the_links_are_named_and_the_meta_are_shapes_like_the_attributes(): void
    {
        $definition = $this->compile(TypedResource::class);

        self::assertSame(['self'], $definition->links);
        self::assertSame(['created-at'], $definition->meta?->names());
        self::assertSame(Kind::DateTime, $definition->meta->field('created-at')?->type->kind);
        self::assertSame(['version'], $definition->documentMeta?->names());
        self::assertSame(Kind::Int, $definition->documentMeta->field('version')?->type->kind);
    }

    public function test_a_link_that_is_a_descriptor_gives_an_error(): void
    {
        $resource = new class (null) extends JsonApiResource {
            protected function toLinks(Request $request): iterable
            {
                return ['self' => $this->string()];
            }
        };

        $definition = $this->compile($resource::class);

        self::assertSame([DefinitionCompiler::HOOK_FAILURE], $this->codes($definition));
        self::assertSame([], $definition->links);
    }

    public function test_a_well_written_resource_has_no_diagnostic(): void
    {
        self::assertSame([], $this->compile(TypedResource::class)->diagnostics);
    }

    public function test_a_plain_value_gives_an_error_and_no_value(): void
    {
        $resource = new class (null) extends JsonApiResource {
            public function toAttributes(Request $request): iterable
            {
                return ['title' => 'eager'];
            }

            protected function toLinks(Request $request): iterable
            {
                return ['self' => 'https://example.com'];
            }
        };

        $definition = $this->compile($resource::class);

        self::assertSame([DefinitionCompiler::INVALID_DECLARATION], $this->codes($definition));
        self::assertSame(DiagnosticLevel::Error, $definition->diagnostics[0]->level);
        self::assertStringContainsString('[title] of toAttributes()', $definition->diagnostics[0]->message);
        self::assertNull($definition->attributes);
        self::assertNull($definition->meta);
        self::assertSame(['self'], $definition->links);
    }

    public function test_a_failing_hook_gives_an_error(): void
    {
        $definition = $this->compile(FailingHookResource::class);

        self::assertSame('failing-hook', $definition->type);
        self::assertSame([DefinitionCompiler::INVALID_DECLARATION], $this->codes($definition));
        self::assertStringContainsString('Needs a real model.', $definition->diagnostics[0]->message);
    }

    public function test_a_resource_mixing_two_enums_gives_an_error(): void
    {
        $resource = new class (null) extends JsonApiResource {
            public function toAttributes(Request $request): iterable
            {
                return [
                    'title' => $this->string()->was(ApiRevision::Labels, name: 'label'),
                    'body' => $this->string()->until(OtherRevision::First),
                ];
            }
        };

        self::assertSame([DefinitionCompiler::INVALID_DECLARATION], $this->codes($this->compile($resource::class, self::revised())));
    }

    public function test_a_resource_rendering_every_linkage_only_when_included_says_so_in_its_definition(): void
    {
        $resource = new class (null) extends JsonApiResource {
            protected ?bool $autoWhenIncluded = true;

            protected function toRelationships(Request $request): iterable
            {
                return [
                    'author' => $this->one(UserResource::class),
                    'reviewer' => $this->one(UserResource::class)->whenIncluded(false),
                ];
            }
        };

        $definition = $this->compile($resource::class);

        self::assertSame(Linkage::Included, $definition->relationships['author']->linkage);
        self::assertSame(Linkage::Always, $definition->relationships['reviewer']->linkage);
    }

    public function test_a_resource_rendering_every_linkage_always_says_so_for_the_relationships_without_their_own(): void
    {
        $resource = new class (null) extends JsonApiResource {
            protected ?bool $autoWhenIncluded = false;

            protected function toRelationships(Request $request): iterable
            {
                return [
                    'author' => $this->one(UserResource::class),
                    'reviewer' => $this->one(UserResource::class)->whenIncluded(),
                ];
            }
        };

        $definition = $this->compile($resource::class);

        self::assertSame(Linkage::Always, $definition->relationships['author']->linkage);
        self::assertSame(Linkage::Included, $definition->relationships['reviewer']->linkage);
    }

    public function test_compiling_a_class_that_is_not_a_resource_throws(): void
    {
        $this->expectException(DefinitionException::class);

        $this->compile(stdClass::class);
    }

    public function test_the_compiled_definition_survives_var_export_without_its_values_and_serialize_with_them(): void
    {
        $definition = $this->compile(TypedResource::class);
        $exported = $definition->values(null, null, null);

        self::assertEquals($exported, eval('return ' . var_export($exported, true) . ';'));
        self::assertEquals($definition, unserialize(serialize($definition)));
    }

    public function test_load_constraints_give_the_closures_left_out_of_the_definition(): void
    {
        $constraints = (new DefinitionCompiler(Configuration::defaults()))->loadConstraints(TypedResource::class);

        self::assertSame(['comments'], array_keys($constraints));
        self::assertSame(['comments'], array_keys($constraints['comments']));
    }

    public function test_load_constraints_follow_the_name_of_the_relationship_at_the_revision(): void
    {
        $compiler = new DefinitionCompiler(self::revised());

        self::assertSame(['remarks'], array_keys($compiler->loadConstraints(ProfileResource::class)));
        self::assertSame(['comments'], array_keys($compiler->loadConstraints(ProfileResource::class, ApiRevision::Initial)));
    }

    public function test_a_resource_until_a_revision_declares_it(): void
    {
        $lifetime = (new DefinitionCompiler(Configuration::defaults()))->lifetime(TeacherResource::class);

        self::assertNull($lifetime->since);
        self::assertSame(ApiRevision::Labels, $lifetime->until);
    }

    public function test_a_resource_since_a_revision_declares_it(): void
    {
        $lifetime = (new DefinitionCompiler(Configuration::defaults()))->lifetime(MemberResource::class);

        self::assertSame(ApiRevision::UsersReplaceTeachers, $lifetime->since);
        self::assertNull($lifetime->until);
    }

    public function test_a_resource_without_lifetime_exists_at_every_revision(): void
    {
        $lifetime = (new DefinitionCompiler(Configuration::defaults()))->lifetime(UserResource::class);

        self::assertNull($lifetime->since);
        self::assertNull($lifetime->until);
    }

    public function test_a_subclass_does_not_inherit_the_lifetime_of_its_parent(): void
    {
        $lifetime = (new DefinitionCompiler(Configuration::defaults()))->lifetime(TeacherChildResource::class);

        self::assertNull($lifetime->until);
    }

    public function test_a_resource_with_since_after_until_cannot_be_compiled(): void
    {
        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage(EmptyLifetimeResource::class);

        $this->compile(EmptyLifetimeResource::class, self::revised());
    }

    public function test_the_definition_names_the_lifetime_of_the_resource(): void
    {
        $definition = $this->compile(TeacherResource::class, self::revised());

        self::assertNull($definition->since);
        self::assertSame(ApiRevision::Labels, $definition->until);
        self::assertNull($definition->revision);
    }

    public function test_the_head_is_the_newest_revision_of_the_application(): void
    {
        self::assertSame(ApiRevision::UsersReplaceTeachers, (new DefinitionCompiler(self::revised()))->head());
        self::assertSame('Head', (new DefinitionCompiler(Configuration::defaults()))->head()->name);
    }

    public function test_a_definition_is_compiled_for_a_revision_in_its_names(): void
    {
        $initial = $this->compile(ProfileResource::class, self::revised(), ApiRevision::Initial);
        $labels = $this->compile(ProfileResource::class, self::revised(), ApiRevision::Labels);
        $head = $this->compile(ProfileResource::class, self::revised());

        self::assertSame(ApiRevision::Initial, $initial->revision);
        self::assertSame(['name', 'email', 'full_name'], $initial->attributes?->names());
        self::assertSame(['comments'], array_keys($initial->relationships));
        self::assertSame(['label', 'email', 'full_name'], $labels->attributes?->names());
        self::assertSame(['remarks'], array_keys($labels->relationships));
        self::assertNull($head->revision);
        self::assertSame(['display_name', 'email', 'tag_names'], $head->attributes?->names());
        self::assertEquals($head, $this->compile(ProfileResource::class, self::revised(), ApiRevision::UsersReplaceTeachers));
    }

    public function test_a_definition_for_a_revision_maps_the_names_of_the_code_to_its_own(): void
    {
        $initial = $this->compile(ProfileResource::class, self::revised(), ApiRevision::Initial);

        self::assertSame(
            ['display_name' => 'name', 'email' => 'email', 'full_name' => 'full_name', 'remarks' => 'comments'],
            $initial->fieldNames,
        );
    }

    public function test_every_definition_of_a_resource_is_compiled_the_head_first(): void
    {
        $definitions = (new DefinitionCompiler(self::revised()))->compileEvery(TeacherResource::class);

        self::assertSame([null, ApiRevision::Labels, ApiRevision::Initial], array_map(
            static fn(ResourceDefinition $definition) => $definition->revision,
            $definitions,
        ));
    }

    public function test_a_resource_compiled_at_a_revision_it_does_not_exist_at_throws(): void
    {
        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage(MemberResource::class);

        $this->compile(MemberResource::class, self::revised(), ApiRevision::Initial);
    }
}
