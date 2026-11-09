<?php

declare(strict_types=1);

namespace Test\Feature\Revisions;

use Ark4ne\JsonApi\Compilation\Definitions;
use PHPUnit\Framework\Attributes\TestWith;
use Test\app\Http\Resources\Revisions\UserResource;
use Test\app\Revisions\ApiRevision;

/**
 * The attributes and the meta of a resource at a revision (docs/rfc/versioned-resources-epics.md US 4.1).
 */
final class AttributesTest extends RevisionsTestCase
{
    public function test_each_revision_renders_its_own_attributes(): void
    {
        $this->assertDocument('head/user', $this->fetch('revisions/users/1')[0]);
        $this->assertDocument('head/user', $this->fetch('revisions/users/1', ApiRevision::UsersReplaceTeachers)[0]);
        $this->assertDocument('labels/user', $this->fetch('revisions/users/1', ApiRevision::Labels)[0]);
        $this->assertDocument('initial/user', $this->fetch('revisions/users/1', ApiRevision::Initial)[0]);
    }

    public function test_the_reference_example_renders_one_name_per_revision(): void
    {
        $name = 'User 1';

        $this->fetch('revisions/users/1')[0]->assertJsonPath('data.attributes.display_name', $name)
            ->assertJsonMissingPath('data.attributes.label')
            ->assertJsonMissingPath('data.attributes.name');
        $this->fetch('revisions/users/1', ApiRevision::Labels)[0]->assertJsonPath('data.attributes.label', $name)
            ->assertJsonMissingPath('data.attributes.display_name');
        $this->fetch('revisions/users/1', ApiRevision::Initial)[0]->assertJsonPath('data.attributes.name', $name)
            ->assertJsonMissingPath('data.attributes.label');
    }

    public function test_a_field_until_a_revision_is_rendered_up_to_it_only(): void
    {
        $this->fetch('revisions/users/1')[0]->assertJsonMissingPath('data.attributes.full_name');
        $this->fetch('revisions/users/1', ApiRevision::Labels)[0]->assertJsonPath('data.attributes.full_name', 'User 1 <user1@example.com>');
        $this->fetch('revisions/users/1', ApiRevision::Initial)[0]->assertJsonPath('data.attributes.full_name', 'User 1 <user1@example.com>');
    }

    public function test_a_value_of_a_was_reads_the_head_attribute(): void
    {
        $this->fetch('revisions/users/1')[0]->assertJsonPath('data.attributes.born_at', '2026-01-01T00:00:00+00:00');
        $this->fetch('revisions/users/1', ApiRevision::Initial)[0]->assertJsonPath('data.attributes.born_at', '2026-01-01');
    }

    public function test_the_sparse_fieldset_of_an_old_client_uses_the_old_names(): void
    {
        [$label] = $this->fetch('revisions/users/1?fields[users]=label', ApiRevision::Labels);
        [$head] = $this->fetch('revisions/users/1?fields[users]=display_name', ApiRevision::Labels);

        self::assertSame(['label' => 'User 1'], $label->json('data.attributes'));
        self::assertNull($head->json('data.attributes'));
    }

    public function test_the_resource_and_document_meta_are_those_of_the_revision(): void
    {
        $this->fetch('revisions/users/1')[0]
            ->assertJsonPath('data.meta.updated_at', '2026-01-01T00:00:00+00:00')
            ->assertJsonPath('meta.directory', 'users');
        $this->fetch('revisions/users/1', ApiRevision::Labels)[0]
            ->assertJsonPath('data.meta.modified_at', '2026-01-01T00:00:00+00:00')
            ->assertJsonMissingPath('data.meta.updated_at')
            ->assertJsonPath('meta.catalog', 'users');
    }

    /**
     * The central invariant (docs/rfc/versioned-resources-epics.md §2): the definition and the document of a revision
     * agree on the names, one transformation serving both.
     */
    #[TestWith([null])]
    #[TestWith([ApiRevision::UsersReplaceTeachers])]
    #[TestWith([ApiRevision::Labels])]
    #[TestWith([ApiRevision::Initial])]
    public function test_the_document_of_a_revision_has_the_names_of_its_definition(?ApiRevision $revision): void
    {
        [$response] = $this->fetch('revisions/users/1', $revision);
        $definition = Definitions::for(UserResource::class, $revision);
        $attributes = $response->json('data.attributes');
        $meta = $response->json('data.meta');
        $documentMeta = $response->json('meta');
        self::assertIsArray($attributes);
        self::assertIsArray($meta);
        self::assertIsArray($documentMeta);

        self::assertSame(array_keys($definition->attributes), array_keys($attributes));
        self::assertSame(array_keys($definition->meta), array_keys($meta));
        self::assertSame(array_keys($definition->documentMeta), array_keys($documentMeta));
    }
}
