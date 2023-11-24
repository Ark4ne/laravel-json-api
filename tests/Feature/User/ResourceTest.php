<?php

namespace Test\Feature\User;

use Ark4ne\JsonApi\Support\Config;
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

    public function testShowFailWithAttributes()
    {
        $user = $this->dataSeed();

        $response = $this->getJson("user/{$user->id}?fields[user]=name,unknown&fields[foo]=bar");

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'fields' => [
                'The selected fields is invalid.',
                '"user" doesn\'t have fields "unknown".',
                '"foo" doesn\'t exists.'
            ]
        ]);
    }

    public function testShowFailWithIncludes()
    {
        $user = $this->dataSeed();

        $response = $this->getJson("user/{$user->id}?include=unknown");

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['include' => [
            'The selected include is invalid.',
            '"user" doesn\'t have relationship "unknown".'
        ]]);
    }

    public function testShowFailWithIncludesSub()
    {
        $user = $this->dataSeed();

        $response = $this->getJson("user/{$user->id}?include=posts.unknown");

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['include' => [
            'The selected include is invalid.',
            '"posts" doesn\'t have relationship "unknown"'
        ]]);
    }

    public function testShowMultipleFailures()
    {
        $user = $this->dataSeed();

        $response = $this->getJson("user/{$user->id}?include=posts.one,two&fields[user]=name,one_field&fields[unknown]=some");

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['include' => [
            'The selected include is invalid.',
            '"posts" doesn\'t have relationship "one"',
            '"user" doesn\'t have relationship "two"',
        ], 'fields' => [
            'The selected fields is invalid.',
            '"user" doesn\'t have relationship "one_field"',
            '"unknown" doesn\'t exists.',
        ]]);
    }

    public function testShowBasicAutoWhenIncluded()
    {
        Config::$autoWhenIncluded = true;
        $user = $this->dataSeed();

        $response = $this->get("user/{$user->id}");

        $response->assertExactJson($this->getJsonResult($user, null, null, false));
    }

    private function dataSeed()
    {
        return User::first();
    }

    private function getJsonResult(User $user, ?array $attributes = null, ?array $relationships = null, bool $withIncluded = true): array
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
                    'main-post' => [],
                    'posts' => array_filter([
                        'data' => $withIncluded ? $user->posts->map(fn(Post $post) => ['type' => 'post', 'id' => $post->id])->all() : null,
                        'links' => [
                            'self' => "https://api.example.com/user/{$user->id}/relationships/posts",
                            'related' => "https://api.example.com/user/{$user->id}/posts",
                        ],
                        'meta' => [
                            'total' => $user->posts->count()
                        ]
                    ]),
                    'comments' => array_filter([
                        // when loaded only
                        'data' => $withIncluded && in_array('comments', $relationships ?? [])
                            ? $user->comments->map(fn(Comment $comment) => [
                                'type' => 'comment',
                                'id' => $comment->id
                            ])->all()
                            : null,
                        'links' => [
                            'self' => "https://api.example.com/user/{$user->id}/relationships/comments",
                            'related' => "https://api.example.com/user/{$user->id}/comments",
                        ],
                        'meta' => [
                            'total' => $user->comments->count()
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
