<?php

declare(strict_types=1);

namespace Test\Feature\Loading;

use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\app\Models\Post;
use Test\app\Models\User;
use Test\Feature\FeatureTestCase;
use Test\Feature\Loading\Fixtures\LinkagePostResource;
use Test\Feature\Loading\Fixtures\LinkageUserResource;

/**
 * The relations a document renders are loaded in batch, before rendering: one query per relation and per level, never
 * one per resource (docs/architecture.md §7).
 */
class LinkageTest extends FeatureTestCase
{
    public function test_the_linkage_of_a_collection_is_loaded_in_one_query_per_relation(): void
    {
        $posts = Post::query()->get();

        [$response, $queries] = $this->render(LinkagePostResource::collection($posts));

        self::assertSame(2, $queries);
        $response->assertJsonCount(30, 'data');
        foreach ($posts as $index => $post) {
            $response->assertJsonPath("data.{$index}.relationships.user.data", ['type' => 'user', 'id' => (string) $post->user->id]);
            $response->assertJsonCount(5, "data.{$index}.relationships.comments.data");
        }
    }

    public function test_included_relationships_are_loaded_level_by_level(): void
    {
        [$response, $queries] = $this->render(
            LinkagePostResource::collection(Post::query()->get()),
            'include=comments.user',
        );

        // The users and the comments of the posts, the users of the comments, then the linkage of the included users:
        // their posts. `post` of a comment is not included, `writer` is not a relation, `comments` of a user not loaded.
        self::assertSame(4, $queries);
        self::assertSame(['comment' => 150, 'user' => 10], array_count_values($this->includedTypes($response)));
    }

    public function test_a_single_resource_loads_its_relations_like_a_collection(): void
    {
        [$response, $queries] = $this->render(
            LinkagePostResource::make(Post::query()->firstOrFail()),
            'include=comments.user',
        );

        self::assertSame(4, $queries);
        $response->assertJsonCount(5, 'data.relationships.comments.data');
    }

    /**
     * No N+1: one resource or all of them, the same queries. (The resources of `tests/app` count the posts and comments
     * of each user in the meta of their relationships: those queries are theirs, not the loading's.)
     */
    #[DataProvider('includes')]
    public function test_the_number_of_queries_does_not_depend_on_the_number_of_resources(bool $users, string $include): void
    {
        // Fresh models for each rendering: nothing loaded by the first one.
        $models = static fn() => $users ? User::query()->get() : Post::query()->get();
        $collection = static fn(mixed $models) => $users
            ? LinkageUserResource::collection($models)
            : LinkagePostResource::collection($models);

        [, $one] = $this->render($collection($models()->take(1)), "include={$include}");
        [, $every] = $this->render($collection($all = $models()), "include={$include}");

        self::assertGreaterThan(1, $all->count());
        self::assertSame($one, $every);
    }

    /**
     * @return iterable<string, array{bool, string}> Users or posts, and the include
     */
    public static function includes(): iterable
    {
        yield 'linkage only' => [false, ''];
        yield 'to-one' => [false, 'user'];
        yield 'to-many then to-one' => [false, 'comments.user'];
        yield 'back to the same type' => [true, 'posts.user.posts'];
    }

    public function test_relations_already_loaded_are_not_loaded_again(): void
    {
        $posts = Post::query()->with(['user', 'comments'])->get();

        [, $queries] = $this->render(LinkagePostResource::collection($posts));

        self::assertSame(0, $queries);
    }

    public function test_a_resource_includes_its_own_paths_with_those_of_the_request(): void
    {
        [$response, $queries] = $this->render(
            LinkagePostResource::make(Post::query()->firstOrFail())->include('comments'),
            'include=user',
        );

        // The user and the comments of the post, then the linkage of the included resources: the posts of the user,
        // the users of the comments.
        self::assertSame(4, $queries);
        self::assertEquals(['comment' => 5, 'user' => 1], array_count_values($this->includedTypes($response)));
    }

    public function test_a_collection_includes_its_own_paths(): void
    {
        [$response] = $this->render(LinkagePostResource::collection(Post::query()->get())->include('user'));

        self::assertSame(['user' => 10], array_count_values($this->includedTypes($response)));
    }

    #[DefineEnvironment('linkageWhenIncluded')]
    public function test_nothing_is_loaded_when_only_included_relationships_render_their_linkage(): void
    {
        [$response, $queries] = $this->render(LinkagePostResource::collection(Post::query()->get()));

        self::assertSame(0, $queries);
        $response->assertJsonMissingPath('data.0.relationships');
    }

    #[DefineEnvironment('linkageWhenLoaded')]
    public function test_only_loaded_relations_render_their_linkage_when_the_linkage_is_loaded(): void
    {
        [$response, $queries] = $this->render(LinkagePostResource::collection(Post::query()->with('user')->get()));

        self::assertSame(0, $queries);
        $response->assertJsonPath('data.0.relationships.user.data.type', 'user');
        $response->assertJsonMissingPath('data.0.relationships.comments');
    }

    public function test_a_relationship_when_loaded_is_loaded_only_when_included(): void
    {
        $users = User::query()->get();

        [$response, $queries] = $this->render(LinkageUserResource::collection($users));
        [$included, $queriesWhenIncluded] = $this->render(
            LinkageUserResource::collection(User::query()->get()),
            'include=comments',
        );
        $comments = count($users->firstOrFail()->comments);

        self::assertSame(1, $queries);
        $response->assertJsonMissingPath('data.0.relationships.comments');
        // posts, comments, and the linkage of the comments: their user (`post` is not included).
        self::assertSame(3, $queriesWhenIncluded);
        $included->assertJsonCount($comments, 'data.0.relationships.comments.data');
    }

    public function test_a_load_constraint_applies_to_the_linkage_and_to_the_included_resources(): void
    {
        $users = User::query()->get();

        [$response, $queries] = $this->render(LinkageUserResource::collection($users), 'include=posts');

        $linked = [];
        foreach ($users as $index => $user) {
            $data = $response->json("data.{$index}.relationships.posts.data");
            self::assertIsArray($data);
            $linked = [...$linked, ...array_column($data, 'id')];
        }
        $included = $response->json('included.*.id');

        // posts, then the user and the comments of the posts.
        self::assertSame(3, $queries);
        self::assertNotEmpty($linked);
        self::assertIsArray($included);
        self::assertEqualsCanonicalizing($linked, $included);
        foreach ($linked as $id) {
            self::assertIsString($id);
            self::assertSame(0, (int) $id % 2);
        }
    }

    public function test_resources_that_are_not_eloquent_models_are_read_as_they_are(): void
    {
        $post = (object) ['id' => 1, 'title' => 'Title', 'user' => (object) ['id' => 2, 'name' => 'Name'], 'comments' => []];

        [$response, $queries] = $this->render(LinkagePostResource::collection([$post]));

        self::assertSame(0, $queries);
        $response->assertJsonPath('data.0.relationships.user.data', ['type' => 'user', 'id' => '2']);
        $response->assertJsonPath('data.0.relationships.comments.data', []);
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function linkageWhenIncluded($app): void
    {
        $app['config']->set('jsonapi.relationship.linkage', 'included');
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function linkageWhenLoaded($app): void
    {
        $app['config']->set('jsonapi.relationship.linkage', 'loaded');
    }


}
