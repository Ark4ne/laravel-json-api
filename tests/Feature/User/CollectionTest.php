<?php

namespace Test\Feature\User;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Test\app\Http\Resources\CommentResource;
use Test\app\Http\Resources\PostResource;
use Test\app\Models\Comment;
use Test\app\Models\Post;
use Test\app\Models\User;
use Test\Feature\FeatureTestCase;

class CollectionTest extends FeatureTestCase
{
    public function testGetIndex()
    {
        $users = User::all();

        $expected = $this->getJsonResult($users);

        $response = $this->get('user');
        $response->assertJson($expected);
    }

    private function getJsonResult(Collection $users, ?array $attributes = null, ?array $relationships = null)
    {
        $request = new Request(array_merge(
            ($attributes !== null ? ['fields' => ['user' => implode(',', $attributes)]] : []),
            ($relationships !== null ? ['include' => implode(',', $relationships)] : []),
        ));

        $data = $users->map(fn(User $user) => [
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
        ]);

        $include = $users
            ->map(fn(User $user) => collect()
                ->merge(
                    in_array('posts', $relationships ?? [])
                        ? $user->posts->mapInto(PostResource::class)->map->toArray($request)
                        : []
                )
                ->merge(
                    in_array('comments', $relationships ?? [])
                        ? $user->comments->mapInto(CommentResource::class)->map->toArray($request)
                        : []
                ))
            ->reduce(fn(Collection $all, Collection $value) => $all->merge($value), collect());

        return collect(array_filter([
            'data' => $data,
            'included' => $include->all(),
            "meta" => [
                'current_page' => 1,
                'from' => 1,
                'last_page' => 1,
                'links' => [
                    [
                        'active' => false,
                        'label' => "&laquo; Previous",
                        'url' => null,
                    ],
                    [
                        'active' => true,
                        'label' => '1',
                        'url' => "http://localhost/user?page=1",
                    ],
                    [
                        'active' => false,
                        'label' => "Next &raquo;",
                        'url' => null,
                    ],
                ],
                'path' => 'http://localhost/user',
                'per_page' => 15,
                'to' => 10,
                'total' => 10,
            ],
        ]))
            ->toArray();
    }
}
