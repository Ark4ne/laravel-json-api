<?php

declare(strict_types=1);

namespace Test\Unit\Compilation;

use Ark4ne\JsonApi\Compilation\DefinitionCompiler;
use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Configuration\Linkage;
use Ark4ne\JsonApi\Definition\AttributeDefinition;
use Ark4ne\JsonApi\Definition\Cardinality;
use Ark4ne\JsonApi\Definition\ResourceDefinition;
use Ark4ne\JsonApi\Definition\ValueType;
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
use Test\Unit\Compilation\Fixtures\ReservedNameResource;
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

    private function attribute(ResourceDefinition $definition, string $name): AttributeDefinition
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
            array_keys($this->compile(TypedResource::class)->attributes),
        );
    }

    public function test_each_value_gives_the_type_of_its_shape(): void
    {
        $definition = $this->compile(TypedResource::class);

        self::assertSame(ValueType::String, $this->attribute($definition, 'title')->type);
        self::assertSame(ValueType::Integer, $this->attribute($definition, 'count')->type);
        self::assertSame(ValueType::Number, $this->attribute($definition, 'ratio')->type);
        self::assertSame(ValueType::Boolean, $this->attribute($definition, 'active')->type);
        self::assertSame(ValueType::Date, $this->attribute($definition, 'published-at')->type);
        self::assertSame(ValueType::Enum, $this->attribute($definition, 'state')->type);
        self::assertSame(State::class, $this->attribute($definition, 'state')->enum);
        self::assertSame(ValueType::Array, $this->attribute($definition, 'tags')->type);
        self::assertSame(ValueType::String, $this->attribute($definition, 'tags')->items?->type);
        self::assertSame(ValueType::Struct, $this->attribute($definition, 'address')->type);
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

        self::assertSame(['city', 'geo'], array_keys($address->children));
        self::assertSame(ValueType::Struct, $address->children['geo']->type);
        self::assertSame(ValueType::Number, $address->children['geo']->children['lat']->type);
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
        self::assertTrue($this->attribute($definition, 'content')->sparse);
        self::assertFalse($this->attribute($definition, 'title')->sparse);
    }

    public function test_the_members_of_apply_when_are_kept_and_conditional(): void
    {
        $grouped = $this->attribute($this->compile(TypedResource::class), 'grouped');

        self::assertSame(ValueType::Integer, $grouped->type);
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

    public function test_the_links_are_named_and_the_meta_described_like_attributes(): void
    {
        $definition = $this->compile(TypedResource::class);

        self::assertSame(['self'], array_keys($definition->links));
        self::assertSame(ValueType::Mixed, $definition->links['self']->type);
        self::assertTrue($definition->links['self']->computed);
        self::assertSame(['created-at'], array_keys($definition->meta));
        self::assertSame(ValueType::Date, $definition->meta['created-at']->type);
        self::assertSame(['version'], array_keys($definition->documentMeta));
        self::assertSame(ValueType::Integer, $definition->documentMeta['version']->type);
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
        self::assertSame([], $definition->attributes);
        self::assertSame(['self'], array_keys($definition->links));
    }

    public function test_a_member_named_id_or_type_gives_an_error(): void
    {
        $definition = $this->compile(ReservedNameResource::class);

        self::assertSame([DefinitionCompiler::RESERVED_NAME, DefinitionCompiler::RESERVED_NAME], $this->codes($definition));
        self::assertSame(['id', 'type'], array_map(static fn(Diagnostic $diagnostic) => $diagnostic->subject, $definition->diagnostics));
    }

    public function test_an_attribute_and_a_relationship_of_the_same_name_give_an_error(): void
    {
        $resource = new class (null) extends JsonApiResource {
            public function toAttributes(Request $request): iterable
            {
                return ['author' => $this->string()];
            }

            protected function toRelationships(Request $request): iterable
            {
                return ['author' => $this->one(UserResource::class)];
            }
        };

        self::assertSame([DefinitionCompiler::NAME_COLLISION], $this->codes($this->compile($resource::class)));
    }

    public function test_a_relationship_to_a_resource_absent_from_the_revision_gives_an_error(): void
    {
        $resource = new class (null) extends JsonApiResource {
            protected function toRelationships(Request $request): iterable
            {
                return ['member' => $this->one(MemberResource::class)];
            }
        };

        self::assertSame([], $this->codes($this->compile($resource::class, self::revised())));
        self::assertSame(
            [DefinitionCompiler::UNAVAILABLE_RESOURCE],
            $this->codes($this->compile($resource::class, self::revised(), ApiRevision::Initial)),
        );
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

    public function test_the_compiled_definition_survives_var_export(): void
    {
        $definition = $this->compile(TypedResource::class);

        self::assertEquals($definition, eval('return ' . var_export($definition, true) . ';'));
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
        self::assertSame(['name', 'email', 'full_name'], array_keys($initial->attributes));
        self::assertSame(['comments'], array_keys($initial->relationships));
        self::assertSame(['label', 'email', 'full_name'], array_keys($labels->attributes));
        self::assertSame(['remarks'], array_keys($labels->relationships));
        self::assertNull($head->revision);
        self::assertSame(['display_name', 'email', 'tag_names'], array_keys($head->attributes));
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
