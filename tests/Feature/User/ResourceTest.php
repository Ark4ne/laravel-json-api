<?php

namespace Test\Feature\User;

use DateTimeInterface;
use Illuminate\Http\Request;
use Test\app\Http\Resources\CommentResource;
use Test\app\Http\Resources\PostResource;
use Test\app\Models\Comment;
use Test\app\Models\Post;
use Test\app\Models\User;
use Test\Feature\FeatureTestCase;

class ResourceTest extends FeatureTestCase
{
    public function testShowBasic()
    {
        $user = $this->dataSeed();

        $response = $this->get("user/{$user->id}");

        $response->assertExactJson($this->getJsonResult($user));
    }

    public function testShowWithAttributes()
    {
        $user = $this->dataSeed();

        $response = $this->get("user/{$user->id}?fields[user]=name");

        $response->assertExactJson($this->getJsonResult($user, ['name']));
    }

    public function testShowWithRelationshipsPosts()
    {
        $user = $this->dataSeed();

        $response = $this->get("user/{$user->id}?include=posts");

        $response->assertExactJson($this->getJsonResult($user, null, ['posts']));
    }

    public function testShowWithRelationshipsComments()
    {
        $user = $this->dataSeed();

        $response = $this->get("user/{$user->id}?include=comments");

        $response->assertExactJson($this->getJsonResult($user, null, ['comments']));
    }

    public function testShowWithRelationshipsPostsComments()
    {
        $user = $this->dataSeed();

        $response = $this->get("user/{$user->id}?include=posts,comments");

        $response->assertExactJson($this->getJsonResult($user, null, ['posts', 'comments']));
    }

    public function testShowWithRelationshipsDeepInclude()
    {
        $user = $this->dataSeed();

        $expected = $this->getJsonResult($user, null, ['posts']);
        $expected['included'][] = $expected['data'];

        $response = $this->get("user/{$user->id}?include=posts.user");

        $response->assertExactJson($expected);

        $response = $this->get("user/{$user->id}?include=posts.user.posts.user.posts.user");

        $response->assertExactJson($expected);
    }

    private function dataSeed()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $posts = Post::factory()->for($user)->count(3)->create();
        $users = User::factory()->count(9)->create();
        foreach ($posts as $post) {
            foreach ($users->random(5) as $u) {
                Comment::factory()->for($post)->for($u)->create();
            }
        }

        return $user;
    }

    private function getJsonResult(User $user, ?array $attributes = null, ?array $relationships = null)
    {
        $request = new Request(array_merge(
            ($attributes !== null ? ['fields' => ['user' => implode(',', $attributes)]] : []),
            ($relationships !== null ? ['include' => implode(',', $relationships)] : []),
        ));

        return array_filter([
            'data' => [
                'id' => $user->id,
                'type' => 'user',
                'attributes' => array_filter(array_intersect_key([
                    'name' => $user->name,
                    'email' => $user->email,
                ], array_fill_keys($attributes ?? ['name', 'email'], true))),
                'relationships' => [
                    'posts' => array_filter([
                        'data' => $user->posts->map(fn(Post $post) => ['type' => 'post', 'id' => $post->id])->all(),
                        'links' => [
                            'self' => "https://api.example.com/user/{$user->id}/relationships/posts",
                            'related' => "https://api.example.com/user/{$user->id}/posts",
                        ]
                    ]),
                    'comments' => array_filter([
                        // when loaded only
                        'data' => in_array('comments', $relationships ?? [])
                            ? $user->comments->map(fn(Comment $comment) => [
                                'type' => 'comment',
                                'id' => $comment->id
                            ])->all()
                            : null,
                        'links' => [
                            'self' => "https://api.example.com/user/{$user->id}/relationships/comments",
                            'related' => "https://api.example.com/user/{$user->id}/comments",
                        ]
                    ]),
                ],
                'meta' => [
                    'created_at' => $user->created_at->format(DateTimeInterface::ATOM),
                    'updated_at' => $user->updated_at->format(DateTimeInterface::ATOM),
                ],
            ],
            'included' => collect()
                ->merge(
                    in_array('posts', $relationships ?? [])
                        ? $user->posts->mapInto(PostResource::class)->map->toArray($request)
                        : []
                )
                ->merge(
                    in_array('comments', $relationships ?? [])
                        ? $user->comments->mapInto(CommentResource::class)->map->toArray($request)
                        : []
                )
                ->toArray()
        ]);
    }
}