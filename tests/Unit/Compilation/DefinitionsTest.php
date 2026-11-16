<?php

declare(strict_types=1);

namespace Test\Unit\Compilation;

use Ark4ne\ApiRevisions\Definition\Kind;
use Ark4ne\JsonApi\Compilation\CompiledDefinitions;
use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Compilation\Projection;
use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\ResourceDefinition;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Closure;
use PHPUnit\Framework\TestCase;
use stdClass;
use Test\app\Revisions\ApiRevision;
use Test\app\Revisions\MemberResource;
use Test\app\Revisions\ProfileResource;
use Test\app\Revisions\TeacherResource;
use Test\app\Schemas\ArticleResource;
use Test\Unit\Compilation\Fixtures\AttributeTypedResource;
use Test\Unit\Compilation\Fixtures\BlogPostResource;
use Test\Unit\Compilation\Fixtures\CountedHooksResource;
use Test\Unit\Compilation\Fixtures\FailingHookResource;
use Test\Unit\Compilation\Fixtures\TypedResource;

final class DefinitionsTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/jsonapi-definitions-' . uniqid() . '.php';
        Definitions::configure(Configuration::defaults());
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        Definitions::configure(Configuration::defaults());
    }

    public function test_a_definition_is_compiled_on_first_use(): void
    {
        $definition = Definitions::for(TypedResource::class);

        self::assertSame('typed', $definition->type);
        self::assertSame([TypedResource::class], array_keys(Definitions::all()));
    }

    public function test_a_definition_is_compiled_once(): void
    {
        self::assertSame(Definitions::for(TypedResource::class), Definitions::for(TypedResource::class));
    }

    public function test_a_class_that_is_not_a_resource_throws(): void
    {
        $this->expectException(DefinitionException::class);

        /** @phpstan-ignore argument.type */
        Definitions::for(stdClass::class);
    }

    public function test_a_resource_whose_values_cannot_be_compiled_throws(): void
    {
        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('Needs a real model.');

        Definitions::for(FailingHookResource::class);
    }

    public function test_the_hooks_of_a_resource_run_once_for_its_definitions_at_every_revision(): void
    {
        Definitions::configure(Configuration::defaults()->withRevisions(ApiRevision::class));
        CountedHooksResource::runs();

        self::assertNotNull(Definitions::for(CountedHooksResource::class)->attribute('title'));
        self::assertNotNull(Definitions::for(CountedHooksResource::class, ApiRevision::Initial)->attribute('name'));
        self::assertSame(['parent'], array_keys(Definitions::loadConstraints(CountedHooksResource::class)));
        self::assertSame(['parent'], array_keys(Definitions::loadConstraints(CountedHooksResource::class, ApiRevision::Initial)));

        self::assertSame([1, 1], CountedHooksResource::runs());

        // What the hooks read from the configuration may change with it.
        Definitions::configure(Configuration::defaults()->withRevisions(ApiRevision::class));
        Definitions::for(CountedHooksResource::class);

        self::assertSame([1, 1], CountedHooksResource::runs());
    }

    public function test_the_configuration_is_used_to_compile(): void
    {
        Definitions::configure(Configuration::fromArray(['describer' => ['nullable' => false]]));

        self::assertFalse(Definitions::for(TypedResource::class)->attribute('title')?->nullable);
    }

    public function test_the_configuration_set_at_boot_is_given_to_the_resources(): void
    {
        $configuration = Configuration::fromArray(['describer' => ['nullable' => false]]);

        Definitions::configure($configuration);

        self::assertSame($configuration, Definitions::configuration());
    }

    public function test_configuring_flushes_the_cache(): void
    {
        Definitions::for(TypedResource::class);

        Definitions::configure(Configuration::defaults());

        self::assertSame([], Definitions::all());
    }

    public function test_flush_empties_the_cache(): void
    {
        Definitions::for(TypedResource::class);

        Definitions::flush();

        self::assertSame([], Definitions::all());
    }

    public function test_preloaded_definitions_are_not_compiled_again(): void
    {
        $predefined = ResourceDefinition::of(TypedResource::class, 'preloaded')->links('preloaded');

        Definitions::preload([TypedResource::class => $predefined]);

        self::assertSame('preloaded', Definitions::for(TypedResource::class)->type);
        self::assertSame(['preloaded'], Definitions::for(TypedResource::class)->links);
        self::assertSame(Definitions::for(TypedResource::class), Definitions::for(TypedResource::class));
    }

    public function test_a_preloaded_definition_takes_the_shapes_of_its_resource_when_asked_for(): void
    {
        Definitions::configure(Configuration::defaults()->withRevisions(ApiRevision::class));
        $compiled = Definitions::compile(ArticleResource::class, ApiRevision::Initial);
        $head = Definitions::compile(ArticleResource::class);

        Definitions::preload([
            ArticleResource::class => $head->values(null, null, null),
            ArticleResource::class . '@Initial' => $compiled->values(null, null, null),
        ]);

        self::assertEquals($compiled, Definitions::for(ArticleResource::class, ApiRevision::Initial));
        self::assertSame(['name', 'views'], Definitions::for(ArticleResource::class, ApiRevision::Initial)->attributes?->names());
        self::assertSame(['title', 'views', 'summary'], Definitions::for(ArticleResource::class)->attributes?->names());
        self::assertContains('name', Definitions::for(ArticleResource::class, ApiRevision::Initial)->fields());
    }

    public function test_a_preloaded_definition_whose_shapes_cannot_be_compiled_throws(): void
    {
        Definitions::preload([FailingHookResource::class => ResourceDefinition::of(FailingHookResource::class, 'stale')]);

        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('Needs a real model.');

        Definitions::for(FailingHookResource::class);
    }

    public function test_the_compiled_file_holds_no_shape_and_the_definitions_read_from_it_have_theirs(): void
    {
        Definitions::configure(Configuration::defaults()->withRevisions(ApiRevision::class));
        $compiled = Definitions::compile(ArticleResource::class, ApiRevision::Initial);
        $file = new CompiledDefinitions($this->file);
        $file->write([CompiledDefinitions::key($compiled) => $compiled]);

        self::assertStringNotContainsString('ApiRevisions\\Compilation', (string) file_get_contents($this->file));
        self::assertNull($file->read()[CompiledDefinitions::key($compiled)]->attributes);

        Definitions::configure(Configuration::defaults()->withRevisions(ApiRevision::class), $file);

        self::assertEquals($compiled, Definitions::for(ArticleResource::class, ApiRevision::Initial));
    }

    public function test_a_compiled_file_of_another_version_is_ignored(): void
    {
        file_put_contents($this->file, '<?php return [' . var_export(TypedResource::class, true) . ' => \\Ark4ne\\JsonApi\\Definition\\AttributeDefinition::__set_state([])];');

        Definitions::configure(Configuration::defaults(), new CompiledDefinitions($this->file));

        self::assertSame('typed', Definitions::for(TypedResource::class)->type);
    }

    public function test_the_compiled_file_is_not_read_when_configuring(): void
    {
        file_put_contents($this->file, '<?php throw new \RuntimeException("read too early");');

        Definitions::configure(Configuration::defaults(), new CompiledDefinitions($this->file));

        self::assertSame([], Definitions::all());
    }

    public function test_the_compiled_file_is_read_on_first_use(): void
    {
        (new CompiledDefinitions($this->file))->write([
            TypedResource::class => ResourceDefinition::of(TypedResource::class, 'from-file'),
        ]);

        Definitions::configure(Configuration::defaults(), new CompiledDefinitions($this->file));

        self::assertSame('from-file', Definitions::for(TypedResource::class)->type);
    }

    public function test_a_class_missing_from_the_compiled_file_is_compiled(): void
    {
        (new CompiledDefinitions($this->file))->write([]);

        Definitions::configure(Configuration::defaults(), new CompiledDefinitions($this->file));

        self::assertSame('typed', Definitions::for(TypedResource::class)->type);
    }

    public function test_a_missing_compiled_file_is_ignored(): void
    {
        Definitions::configure(Configuration::defaults(), new CompiledDefinitions($this->file));

        self::assertSame('typed', Definitions::for(TypedResource::class)->type);
    }

    public function test_compile_ignores_the_cache_and_uses_the_configuration(): void
    {
        Definitions::configure(Configuration::fromArray(['describer' => ['nullable' => false]]));
        Definitions::preload([TypedResource::class => ResourceDefinition::of(TypedResource::class, 'preloaded')]);

        $definition = Definitions::compile(TypedResource::class);

        self::assertSame('typed', $definition->type);
        self::assertFalse($definition->attribute('title')?->nullable);
        self::assertSame('preloaded', Definitions::for(TypedResource::class)->type);
    }

    public function test_load_constraints_are_kept_by_class(): void
    {
        $constraints = Definitions::loadConstraints(TypedResource::class);

        self::assertInstanceOf(Closure::class, $constraints['comments']['comments'] ?? null);
        self::assertSame([], Definitions::loadConstraints(BlogPostResource::class));
    }

    public function test_the_declared_type_follows_the_configuration_set_at_boot(): void
    {
        self::assertSame('blog-post', Definitions::declaredType(BlogPostResource::class));
        self::assertSame('articles', Definitions::declaredType(AttributeTypedResource::class));

        Definitions::configure(Configuration::fromArray(['type' => ['naming' => 'kebab-plural']]));

        self::assertSame('blog-posts', Definitions::declaredType(BlogPostResource::class));
    }

    public function test_the_lifetime_of_a_resource_is_read_from_its_attributes(): void
    {
        $lifetime = Definitions::lifetime(TeacherResource::class);

        self::assertSame(ApiRevision::Labels, $lifetime->until);
        self::assertEquals($lifetime, Definitions::lifetime(TeacherResource::class));
    }

    public function test_the_definition_of_a_revision_has_its_names(): void
    {
        Definitions::configure(Configuration::defaults()->withRevisions(ApiRevision::class));

        $initial = Definitions::for(ProfileResource::class, ApiRevision::Initial);
        $labels = Definitions::for(ProfileResource::class, ApiRevision::Labels);
        $head = Definitions::for(ProfileResource::class);

        self::assertSame(['name', 'email', 'full_name'], $initial->attributes?->names());
        self::assertSame(['comments'], array_keys($initial->relationships));
        self::assertSame(ApiRevision::Initial, $initial->revision);
        self::assertSame(['label', 'email', 'full_name'], $labels->attributes?->names());
        self::assertSame(['remarks'], array_keys($labels->relationships));
        self::assertSame(ApiRevision::Labels, $labels->revision);
        self::assertSame(['display_name', 'email', 'tag_names'], $head->attributes?->names());
        self::assertNull($head->revision);
    }

    public function test_a_renamed_relationship_keeps_the_source_of_the_head(): void
    {
        Definitions::configure(Configuration::defaults()->withRevisions(ApiRevision::class));

        self::assertSame('comments', Definitions::for(ProfileResource::class, ApiRevision::Initial)->relationships['comments']->source);
    }

    public function test_two_revisions_with_the_same_shape_have_the_same_members(): void
    {
        Definitions::configure(Configuration::defaults()->withRevisions(ApiRevision::class));

        // Declared on the base: one shape per revision, compiled and cached by it.
        $initial = Definitions::for(TeacherResource::class, ApiRevision::Initial);

        self::assertSame($initial, Definitions::for(TeacherResource::class, ApiRevision::Initial));
        self::assertSame(['label'], $initial->attributes?->names());
        self::assertSame(['label'], Definitions::for(TeacherResource::class, ApiRevision::Labels)->attributes?->names());
    }

    public function test_a_revision_newer_than_every_declaration_reads_the_head(): void
    {
        Definitions::configure(Configuration::defaults()->withRevisions(ApiRevision::class));

        self::assertSame(
            Definitions::for(ProfileResource::class),
            Definitions::for(ProfileResource::class, ApiRevision::UsersReplaceTeachers),
        );
    }

    public function test_a_resource_without_history_has_the_same_members_at_every_revision(): void
    {
        Definitions::configure(Configuration::defaults()->withRevisions(ApiRevision::class));

        $definition = Definitions::for(TypedResource::class);
        $initial = Definitions::for(TypedResource::class, ApiRevision::Initial);

        self::assertSame(ApiRevision::Initial, $initial->revision);
        self::assertSame($definition->attributes?->names(), $initial->attributes?->names());
        self::assertSame($definition->fieldNames, $initial->fieldNames);
    }

    public function test_a_resource_since_a_revision_does_not_exist_before_it(): void
    {
        Definitions::configure(Configuration::defaults()->withRevisions(ApiRevision::class));

        self::assertFalse(Definitions::exists(MemberResource::class, ApiRevision::Labels));
        self::assertTrue(Definitions::exists(MemberResource::class, ApiRevision::UsersReplaceTeachers));
        self::assertTrue(Definitions::exists(MemberResource::class, null));

        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('Labels');

        Definitions::for(MemberResource::class, ApiRevision::Labels);
    }

    public function test_a_resource_until_a_revision_does_not_exist_after_it(): void
    {
        Definitions::configure(Configuration::defaults()->withRevisions(ApiRevision::class));

        self::assertTrue(Definitions::exists(TeacherResource::class, ApiRevision::Labels));
        self::assertFalse(Definitions::exists(TeacherResource::class, ApiRevision::UsersReplaceTeachers));
        self::assertFalse(Definitions::exists(TeacherResource::class, null));
    }

    public function test_load_constraints_follow_the_renamed_relationship(): void
    {
        Definitions::configure(Configuration::defaults()->withRevisions(ApiRevision::class));

        self::assertSame(['remarks'], array_keys(Definitions::loadConstraints(ProfileResource::class)));
        self::assertSame(['comments'], array_keys(Definitions::loadConstraints(ProfileResource::class, ApiRevision::Initial)));
        self::assertSame(['remarks'], array_keys(Definitions::loadConstraints(ProfileResource::class, ApiRevision::Labels)));
    }

    public function test_flush_empties_the_definitions_of_the_revisions(): void
    {
        Definitions::configure(Configuration::defaults()->withRevisions(ApiRevision::class));

        $initial = Definitions::for(ProfileResource::class, ApiRevision::Initial);
        Definitions::flush();

        self::assertNotSame($initial, Definitions::for(ProfileResource::class, ApiRevision::Initial));
    }

    public function test_a_compiled_file_holding_the_head_only_is_read(): void
    {
        Definitions::configure(Configuration::defaults()->withRevisions(ApiRevision::class));

        $head = ResourceDefinition::of(ProfileResource::class, 'preloaded');
        Definitions::preload([ProfileResource::class => $head]);

        self::assertSame('preloaded', Definitions::for(ProfileResource::class)->type);
        // A revision missing from the file is compiled.
        self::assertSame('profiles', Definitions::for(ProfileResource::class, ApiRevision::Initial)->type);
    }

    public function test_preloaded_definitions_of_revisions_are_kept_by_revision(): void
    {
        Definitions::configure(Configuration::defaults()->withRevisions(ApiRevision::class));

        $head = ResourceDefinition::of(ProfileResource::class, 'head');
        $initial = ResourceDefinition::of(ProfileResource::class, 'initial')->revision(ApiRevision::Initial);
        Definitions::preload([ProfileResource::class => $head, ProfileResource::class . '@Initial' => $initial]);

        self::assertSame('initial', Definitions::for(ProfileResource::class, ApiRevision::Initial)->type);
        self::assertSame(ApiRevision::Initial, Definitions::for(ProfileResource::class, ApiRevision::Initial)->attributes?->revision);
        self::assertSame('head', Definitions::for(ProfileResource::class, ApiRevision::UsersReplaceTeachers)->type);
        self::assertSame(ApiRevision::Labels, Definitions::for(ProfileResource::class, ApiRevision::Labels)->revision);
    }

    public function test_the_projection_is_the_one_the_application_gives(): void
    {
        $projection = new Projection();
        Definitions::projectWith(static fn(): Projection => $projection);

        self::assertSame($projection, Definitions::projection());

        Definitions::projectWith(null);

        self::assertNotSame($projection, Definitions::projection());
        self::assertSame(Definitions::projection(), Definitions::projection());
    }

    public function test_a_resource_declared_on_the_base_is_defined_from_its_shape_at_the_revision(): void
    {
        Definitions::configure(Configuration::defaults()->withRevisions(ApiRevision::class));

        $initial = Definitions::for(ArticleResource::class, ApiRevision::Initial);
        $head = Definitions::for(ArticleResource::class, ApiRevision::UsersReplaceTeachers);

        self::assertSame('articles', $initial->type);
        self::assertSame(['name', 'views'], $initial->attributes?->names());
        self::assertSame(Kind::String, $initial->attribute('views')?->type->kind);
        self::assertSame(ApiRevision::Initial, $initial->attributes->revision);
        self::assertSame(['published'], $initial->meta?->names());
        self::assertSame(['legacy'], $initial->documentMeta?->names());
        self::assertSame(['title' => 'name', 'views' => 'views'], $initial->fieldNames);

        self::assertSame(['title', 'views', 'summary'], $head->attributes?->names());
        self::assertSame(Kind::Int, $head->attribute('views')?->type->kind);
        self::assertTrue($head->attribute('summary')?->nullable);
        self::assertSame([], $head->documentMeta?->names());
        // By the name at the revision, not by the key of the code.
        self::assertNull($initial->attribute('title'));
        self::assertSame('title', $initial->attribute('name')?->headKey);
    }

    public function test_the_head_of_a_resource_declared_on_the_base_is_its_newest_revision(): void
    {
        Definitions::configure(Configuration::defaults()->withRevisions(ApiRevision::class));

        self::assertSame(['title', 'views', 'summary'], Definitions::for(ArticleResource::class)->attributes?->names());
        self::assertSame(Definitions::for(ArticleResource::class), Definitions::for(ArticleResource::class));
    }

    public function test_the_definition_of_a_revision_is_read_once(): void
    {
        Definitions::configure(Configuration::defaults()->withRevisions(ApiRevision::class));

        self::assertSame(
            Definitions::for(ArticleResource::class, ApiRevision::Labels),
            Definitions::for(ArticleResource::class, ApiRevision::Labels),
        );
    }
}
