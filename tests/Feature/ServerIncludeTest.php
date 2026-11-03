<?php

declare(strict_types=1);

namespace Test\Feature;

use Test\app\Http\Resources\UserResource;
use Test\app\Models\Post;
use Test\app\Models\User;
use Test\Feature\Loading\Fixtures\LinkageUserResource;

/**
 * Relationship paths included by the server whatever the request (`->include()`), as asked by issue #33.
 */
final class ServerIncludeTest extends FeatureTestCase
{
    public function test_a_resource_includes_a_nested_path_the_request_does_not_ask_for(): void
    {
        $user = User::query()->has('posts.comments')->firstOrFail();

        [$response] = $this->render(UserResource::make($user)->include('posts.comments'));

        self::assertEquals(
            ['post' => $user->posts->count(), 'comment' => $this->commentsOfPosts($user)],
            array_count_values($this->includedTypes($response)),
        );
    }

    public function test_the_paths_of_the_server_are_added_to_those_of_the_request(): void
    {
        $user = User::query()->has('posts.comments')->firstOrFail();

        [$response] = $this->render(UserResource::make($user)->include('posts.comments'), 'include=main-post');

        $types = array_count_values($this->includedTypes($response));
        self::assertSame($user->posts->count(), $types['post']);
        self::assertSame($this->commentsOfPosts($user), $types['comment']);
    }

    public function test_a_collection_includes_a_nested_path_in_a_constant_number_of_queries(): void
    {
        // Without the meta of UserResource, which count the posts and comments of each user.
        [, $one] = $this->render(LinkageUserResource::collection([User::query()->firstOrFail()])->include('posts.comments'));
        [$response, $all] = $this->render(LinkageUserResource::collection(User::query()->get())->include('posts.comments'));

        self::assertSame($one, $all);
        self::assertGreaterThan(0, array_count_values($this->includedTypes($response))['comment'] ?? 0);
    }

    private function commentsOfPosts(User $user): int
    {
        return $user->posts->sum(static fn(Post $post): int => $post->comments->count());
    }
}
