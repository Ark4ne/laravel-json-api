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
use DateTimeInterface;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use stdClass;
use Test\app\Enums\State;
use Test\app\Http\Resources\CommentResource;
use Test\app\Http\Resources\UserResource;
use Test\app\Revisions\ApiRevision;
use Test\app\Revisions\MemberResource;
use Test\app\Revisions\ProfileResource;
use Test\app\Revisions\TeacherChildResource;
use Test\app\Revisions\TeacherResource;
use Test\Unit\Compilation\Fixtures\AttributeTypedResource;
use Test\Unit\Compilation\Fixtures\BlogPostResource;
use Test\Unit\Compilation\Fixtures\EagerResource;
use Test\Unit\Compilation\Fixtures\EmptyLifetimeResource;
use Test\Unit\Compilation\Fixtures\FailingHookResource;
use Test\Unit\Compilation\Fixtures\InvalidMemberResource;
use Test\Unit\Compilation\Fixtures\MergeWhenResource;
use Test\Unit\Compilation\Fixtures\OverriddenTypeResource;
use Test\Unit\Compilation\Fixtures\PredefinedResource;
use Test\Unit\Compilation\Fixtures\PrefixedTypeNaming;
use Test\Unit\Compilation\Fixtures\ReservedNameResource;
use Test\Unit\Compilation\Fixtures\StructResource;
use Test\Unit\Compilation\Fixtures\TypedResource;
use Test\Unit\Compilation\Fixtures\UnnamedMembersResource;

final class DefinitionCompilerTest extends TestCase
{
    private function compile(string $class, ?Configuration $configuration = null): ResourceDefinition
    {
        /** @var class-string<\Ark4ne\JsonApi\Definition\DefinitionSource> $class */
        return (new DefinitionCompiler($configuration ?? Configuration::defaults()))->compile($class);
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
            ['title', 'name', 'count', 'ratio', 'active', 'published-at', 'state', 'tags', 'full-name', 'secret',
                'content', 'address', 'grouped'],
            array_keys($this->compile(TypedResource::class)->attributes),
        );
    }

    public function test_each_value_descriptor_gives_its_type(): void
    {
        $definition = $this->compile(TypedResource::class);

        self::assertSame(ValueType::String, $this->attribute($definition, 'title')->type);
        self::assertSame(ValueType::Integer, $this->attribute($definition, 'count')->type);
        self::assertSame(ValueType::Number, $this->attribute($definition, 'ratio')->type);
        self::assertSame(ValueType::Boolean, $this->attribute($definition, 'active')->type);
        self::assertSame(ValueType::Date, $this->attribute($definition, 'published-at')->type);
        self::assertSame(ValueType::Enum, $this->attribute($definition, 'state')->type);
        self::assertSame(ValueType::Array, $this->attribute($definition, 'tags')->type);
        self::assertSame(ValueType::Struct, $this->attribute($definition, 'address')->type);
    }

    public function test_the_source_is_the_attribute_read_on_the_model(): void
    {
        $definition = $this->compile(TypedResource::class);

        self::assertSame('title', $this->attribute($definition, 'title')->source);
        self::assertSame('first_name', $this->attribute($definition, 'name')->source);
        self::assertFalse($this->attribute($definition, 'title')->computed);
    }

    public function test_a_closure_value_is_computed_without_source(): void
    {
        $fullName = $this->attribute($this->compile(TypedResource::class), 'full-name');

        self::assertSame(ValueType::String, $fullName->type);
        self::assertTrue($fullName->computed);
        self::assertNull($fullName->source);
    }

    public function test_nullable_defaults_to_the_configuration_unless_the_descriptor_says_otherwise(): void
    {
        $definition = $this->compile(TypedResource::class);
        self::assertTrue($this->attribute($definition, 'title')->nullable);
        self::assertFalse($this->attribute($definition, 'name')->nullable);

        $strict = $this->compile(
            TypedResource::class,
            Configuration::fromArray(['describer' => ['nullable' => false]]),
        );
        self::assertFalse($this->attribute($strict, 'title')->nullable);
    }

    public function test_a_date_format_defaults_to_the_configuration_unless_the_descriptor_says_otherwise(): void
    {
        $definition = $this->compile(TypedResource::class);

        self::assertSame('Y-m-d', $this->attribute($definition, 'published-at')->format);
        self::assertSame(DateTimeInterface::ATOM, $definition->meta['created-at']->format);
    }

    public function test_an_enum_value_records_its_enum_class(): void
    {
        self::assertSame(State::class, $this->attribute($this->compile(TypedResource::class), 'state')->enum);
    }

    public function test_a_typed_array_records_the_type_of_its_items(): void
    {
        $tags = $this->attribute($this->compile(TypedResource::class), 'tags');

        self::assertNotNull($tags->items);
        self::assertSame(ValueType::String, $tags->items->type);
    }

    public function test_a_struct_records_its_members_recursively(): void
    {
        $address = $this->attribute($this->compile(TypedResource::class), 'address');

        self::assertSame(['city', 'geo'], array_keys($address->children));
        self::assertSame(ValueType::String, $address->children['city']->type);
        self::assertSame(ValueType::Number, $address->children['geo']->children['lat']->type);
    }

    public function test_conditions_mark_an_attribute_conditional(): void
    {
        $definition = $this->compile(TypedResource::class);

        self::assertFalse($this->attribute($definition, 'title')->conditional);
        self::assertTrue($this->attribute($definition, 'secret')->conditional);
        self::assertFalse($this->attribute($definition, 'secret')->sparse);
    }

    public function test_when_in_fields_marks_an_attribute_conditional_and_sparse(): void
    {
        $content = $this->attribute($this->compile(TypedResource::class), 'content');

        self::assertTrue($content->conditional);
        self::assertTrue($content->sparse);
    }

    public function test_the_members_of_apply_when_are_kept_and_conditional(): void
    {
        $grouped = $this->attribute($this->compile(TypedResource::class), 'grouped');

        self::assertSame(ValueType::Integer, $grouped->type);
        self::assertTrue($grouped->conditional);
    }

    public function test_the_members_of_merge_when_are_hidden_with_a_diagnostic_whatever_the_condition(): void
    {
        $definition = $this->compile(MergeWhenResource::class);

        self::assertSame(['title', 'merged'], array_keys($definition->attributes));
        self::assertSame([], $definition->relationships);
        self::assertSame(
            [
                DefinitionCompiler::MERGE_WHEN,
                DefinitionCompiler::MERGE_WHEN,
                DefinitionCompiler::MERGE_WHEN,
                DefinitionCompiler::UNNAMED_MEMBER,
                DefinitionCompiler::MERGE_WHEN,
                DefinitionCompiler::MERGE_WHEN,
            ],
            $this->codes($definition),
        );
    }

    public function test_members_without_key_are_named_after_what_they_read(): void
    {
        $definition = $this->compile(UnnamedMembersResource::class);

        self::assertSame(['title'], array_keys($definition->attributes));
        self::assertSame(['author'], array_keys($definition->relationships));
        self::assertSame([], $definition->diagnostics);
    }

    public function test_the_hooks_run_once(): void
    {
        StructResource::$reads = 0;

        $this->compile(StructResource::class);

        self::assertSame(2, StructResource::$reads);
    }

    public function test_the_diagnostics_of_struct_members_are_kept(): void
    {
        $definition = $this->compile(StructResource::class);

        self::assertSame([DefinitionCompiler::EAGER_ATTRIBUTE], $this->codes($definition));
        self::assertSame('city', $definition->diagnostics[0]->subject);
    }

    public function test_an_invalid_member_is_left_out_with_an_error_naming_it(): void
    {
        $definition = $this->compile(InvalidMemberResource::class);
        $errors = array_values(array_filter(
            $definition->diagnostics,
            static fn(Diagnostic $diagnostic): bool => $diagnostic->code === DefinitionCompiler::INVALID_MEMBER,
        ));

        self::assertSame(['title', 'content'], array_keys($definition->attributes));
        self::assertSame(['author'], array_keys($definition->relationships));
        self::assertSame(['broken', 'other'], array_map(static fn(Diagnostic $d): ?string => $d->subject, $errors));
        self::assertSame(DiagnosticLevel::Error, $errors[0]->level);
        self::assertStringContainsString('Needs a real model.', $errors[0]->message);
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

    public function test_links_and_resource_meta_are_described_like_attributes(): void
    {
        $definition = $this->compile(TypedResource::class);

        self::assertSame(['self'], array_keys($definition->links));
        self::assertSame(ValueType::String, $definition->links['self']->type);
        self::assertSame(['created-at'], array_keys($definition->meta));
        self::assertSame(ValueType::Date, $definition->meta['created-at']->type);
    }

    public function test_the_document_meta_is_described_like_attributes(): void
    {
        $definition = $this->compile(TypedResource::class);

        self::assertSame(['version'], array_keys($definition->documentMeta));
        self::assertSame(ValueType::Integer, $definition->documentMeta['version']->type);
        self::assertTrue($definition->documentMeta['version']->computed);
    }

    public function test_a_well_written_resource_has_no_diagnostic(): void
    {
        self::assertSame([], $this->compile(TypedResource::class)->diagnostics);
    }

    public function test_an_eager_attribute_is_mixed_with_the_path_read_on_the_probe(): void
    {
        $definition = $this->compile(EagerResource::class);

        self::assertSame(ValueType::Mixed, $this->attribute($definition, 'title')->type);
        self::assertSame('title', $this->attribute($definition, 'title')->source);
        self::assertSame('user.name', $this->attribute($definition, 'author-name')->source);
    }

    public function test_an_eager_attribute_gives_a_warning(): void
    {
        $diagnostics = $this->compile(EagerResource::class)->diagnostics;

        self::assertCount(2, $diagnostics);
        self::assertSame(DefinitionCompiler::EAGER_ATTRIBUTE, $diagnostics[0]->code);
        self::assertSame(DiagnosticLevel::Warning, $diagnostics[0]->level);
        self::assertSame('title', $diagnostics[0]->subject);
    }

    public function test_a_member_named_id_or_type_gives_an_error(): void
    {
        $definition = $this->compile(ReservedNameResource::class);
        $reserved = array_values(array_filter(
            $definition->diagnostics,
            static fn(Diagnostic $diagnostic): bool => $diagnostic->code === DefinitionCompiler::RESERVED_NAME,
        ));

        self::assertSame(['id', 'type'], array_map(static fn(Diagnostic $d): ?string => $d->subject, $reserved));
        self::assertSame(DiagnosticLevel::Error, $reserved[0]->level);
    }

    public function test_a_failing_hook_keeps_the_other_hooks_and_gives_an_error(): void
    {
        $definition = $this->compile(FailingHookResource::class);

        self::assertSame(['title'], array_keys($definition->attributes));
        self::assertSame([], $definition->relationships);
        self::assertSame([DefinitionCompiler::HOOK_FAILURE], $this->codes($definition));
        self::assertSame(DiagnosticLevel::Error, $definition->diagnostics[0]->level);
        self::assertStringContainsString('Needs a real model.', $definition->diagnostics[0]->message);
    }

    public function test_a_predefined_definition_is_returned_without_running_the_hooks(): void
    {
        $definition = $this->compile(PredefinedResource::class);

        self::assertSame('predefined', $definition->type);
        self::assertSame(['title'], array_keys($definition->attributes));
    }

    public function test_a_resource_rendering_every_linkage_only_when_included_says_so_in_its_definition(): void
    {
        $resource = new class (null) extends JsonApiResource {
            protected ?bool $autoWhenIncluded = true;

            /**
             * @return iterable<string, \Ark4ne\JsonApi\Descriptors\Relations\Relation<mixed>>
             */
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

            /**
             * @return iterable<string, \Ark4ne\JsonApi\Descriptors\Relations\Relation<mixed>>
             */
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

        $this->compile(EmptyLifetimeResource::class);
    }

    public function test_the_definition_names_the_lifetime_of_the_resource(): void
    {
        $definition = $this->compile(TeacherResource::class);

        self::assertNull($definition->since);
        self::assertSame(ApiRevision::Labels, $definition->until);
        self::assertNull($definition->revision);
    }

    public function test_a_definition_is_compiled_for_a_revision(): void
    {
        $compiler = new DefinitionCompiler(Configuration::defaults());

        $labels = $compiler->compile(ProfileResource::class, ApiRevision::Labels);
        $newest = $compiler->compile(ProfileResource::class, ApiRevision::UsersReplaceTeachers);

        self::assertSame(ApiRevision::Labels, $labels->revision);
        self::assertSame(['label', 'email', 'full_name'], array_keys($labels->attributes));
        self::assertNull($newest->revision);
        self::assertSame(['display_name', 'email', 'tag_names'], array_keys($newest->attributes));
    }

    public function test_a_definition_for_a_revision_maps_the_names_of_the_code_to_its_own(): void
    {
        $compiler = new DefinitionCompiler(Configuration::defaults());

        self::assertSame(
            ['display_name' => 'name', 'email' => 'email', 'full_name' => 'full_name', 'remarks' => 'comments'],
            $compiler->compile(ProfileResource::class, ApiRevision::Initial)->fieldNames,
        );
        // The head: the names of the code, without the fields until a revision.
        self::assertSame(
            ['display_name' => 'display_name', 'email' => 'email', 'tag_names' => 'tag_names', 'remarks' => 'remarks'],
            $compiler->compile(ProfileResource::class)->fieldNames,
        );
        self::assertSame([], $compiler->compile(StructResource::class)->fieldNames);
    }

    public function test_every_shape_of_a_resource_is_compiled_with_its_hooks_run_once(): void
    {
        StructResource::$reads = 0;
        $compiler = new DefinitionCompiler(Configuration::defaults());

        self::assertCount(1, $compiler->compileEvery(StructResource::class));
        self::assertSame(2, StructResource::$reads);
        self::assertSame(
            [null, ApiRevision::Initial, ApiRevision::Labels],
            array_map(static fn(ResourceDefinition $definition) => $definition->revision, $compiler->compileEvery(ProfileResource::class)),
        );
    }

    public function test_a_resource_compiled_at_a_revision_it_does_not_exist_at_throws(): void
    {
        $compiler = new DefinitionCompiler(Configuration::defaults());

        self::assertSame(TeacherResource::class, $compiler->compile(TeacherResource::class)->class);

        $this->expectException(DefinitionException::class);
        $compiler->compile(TeacherResource::class, ApiRevision::UsersReplaceTeachers);
    }
}
