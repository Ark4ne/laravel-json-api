<?php

declare(strict_types=1);

namespace Test\Feature\Revisions;

use Ark4ne\JsonApi\Compilation\Definitions;
use Test\app\Http\Resources\Revisions\PostResource;
use Test\app\Revisions\ApiRevision;

/**
 * The relationships and the included resources of a resource at a revision (docs/rfc/versioned-resources-epics.md
 * US 4.2).
 */
final class RelationshipsTest extends RevisionsTestCase
{
    public function test_each_revision_renders_its_own_relationships_and_included_resources(): void
    {
        [$head, $headQueries] = $this->fetch('revisions/posts?include=user,comments.user');
        [$labels, $labelsQueries] = $this->fetch('revisions/posts?include=writer,comments.commenter', ApiRevision::Labels);

        $this->assertDocument('head/posts', $head);
        $this->assertDocument('labels/posts', $labels);
        // The same relations, loaded in batch the same way.
        self::assertSame($headQueries, $labelsQueries);
    }

    public function test_the_same_user_is_included_once_per_old_type(): void
    {
        [$head] = $this->fetch('revisions/posts?include=user,comments.user');
        [$labels] = $this->fetch('revisions/posts?include=writer,comments.commenter', ApiRevision::Labels);

        $users = array_filter($this->includedIdentifiers($head), static fn(array $id) => $id['id'] === '2' && $id['type'] !== 'comments');
        $old = array_filter($this->includedIdentifiers($labels), static fn(array $id) => $id['id'] === '2' && $id['type'] !== 'comments');

        self::assertSame([['type' => 'users', 'id' => '2']], array_values($users));
        self::assertEqualsCanonicalizing(
            [['type' => 'commenters', 'id' => '2'], ['type' => 'writers', 'id' => '2']],
            array_values($old),
        );
    }

    public function test_the_linkage_and_the_included_resource_agree_on_the_old_type(): void
    {
        [$labels] = $this->fetch('revisions/posts/1?include=writer', ApiRevision::Labels);

        $labels->assertJsonPath('data.relationships.writer.data', ['type' => 'writers', 'id' => '1'])
            ->assertJsonMissingPath('data.relationships.user')
            ->assertJsonPath('included.0.type', 'writers')
            ->assertJsonPath('included.0.attributes.name', 'User 1');
    }

    public function test_an_old_nested_include_path_is_rendered(): void
    {
        [$labels] = $this->fetch('revisions/posts/1?include=comments.commenter', ApiRevision::Labels);

        self::assertSame(
            ['comments', 'commenters'],
            array_values(array_unique($this->includedTypes($labels))),
        );
        $labels->assertJsonPath('included.0.relationships.commenter.data.type', 'commenters');
    }

    public function test_an_include_path_of_the_head_includes_nothing_for_an_old_client(): void
    {
        [$labels] = $this->fetch('revisions/posts/1?include=user', ApiRevision::Labels);

        $labels->assertJsonMissingPath('included');
    }

    public function test_the_sparse_fieldset_of_an_old_type_filters_it(): void
    {
        [$labels] = $this->fetch('revisions/posts/1?include=writer&fields[writers]=email', ApiRevision::Labels);

        self::assertSame(['email' => 'user1@example.com'], $labels->json('included.0.attributes'));
    }

    public function test_a_relationship_that_was_to_one_renders_one_identifier(): void
    {
        [$head] = $this->fetch('revisions/users/1?include=posts');
        [$initial, $queries] = $this->fetch('revisions/users/1?include=post', ApiRevision::Initial);

        $posts = $head->json('data.relationships.posts.data');
        self::assertIsArray($posts);
        self::assertCount(3, $posts);
        $initial->assertJsonPath('data.relationships.post.data', ['type' => 'posts', 'id' => '1'])
            ->assertJsonMissingPath('data.relationships.posts');
        self::assertSame(['posts'], $this->includedTypes($initial));
        self::assertSame($queries, $this->fetch('revisions/users/1?include=posts')[1]);
    }

    public function test_the_relationships_of_a_revision_have_the_names_of_its_definition(): void
    {
        foreach ([null, ApiRevision::Labels, ApiRevision::Initial] as $revision) {
            [$response] = $this->fetch('revisions/posts/1?include=user,writer,comments', $revision);
            $relationships = $response->json('data.relationships');
            self::assertIsArray($relationships);

            self::assertSame(
                array_keys(Definitions::for(PostResource::class, $revision)->relationships),
                array_keys($relationships),
            );
        }
    }
}
