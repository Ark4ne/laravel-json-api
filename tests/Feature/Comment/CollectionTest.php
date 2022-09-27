<?php

namespace Test\Feature\Comment;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Test\app\Http\Resources\PostResource;
use Test\app\Http\Resources\UserResource;
use Test\app\Models\Comment;
use Test\app\Models\Post;
use Test\app\Models\User;
use Test\Feature\FeatureTestCase;

class CollectionTest extends FeatureTestCase
{
    public function testIndexBasic()
    {
        $response = $this->get("comment");

        $response->assertExactJson($this->getJsonResult(Comment::query()->limit(15)->get()));
    }

    public function testIndexWithInclude()
    {
        $response = $this->get("comment?include=post");

        $response->assertExactJson($this->getJsonResult(Comment::query()->limit(15)->get(), null, ['post']));
    }

    private function getJsonResult(Collection $comments, ?array $attributes = null, ?array $relationships = null)
    {
        $request = new Request(array_merge(
            ($attributes !== null ? ['fields' => ['comment' => implode(',', $attributes)]] : []),
            ($relationships !== null ? ['include' => implode(',', $relationships)] : []),
        ));

        $data = $comments->map(fn(Comment $comment) => [
            'id' => $comment->id,
            'type' => 'comment',
            'attributes' => array_filter(array_intersect_key([
                'content' => $comment->content,
            ], array_fill_keys($attributes ?? ['content'], true))),
            'relationships' => [
                'user' => array_filter([
                    'data' => in_array('user', $relationships ?? [])
                        ? ['type' => 'user', 'id' => $comment->user->id]
                        : null,
                    'links' => [
                        'self' => "https://api.example.com/comment/{$comment->id}/relationships/user",
                        'related' => "https://api.example.com/comment/{$comment->id}/user",
                    ]
                ]),
                'post' => array_filter([
                    'data' => in_array('post', $relationships ?? [])
                        ? ['type' => 'post', 'id' => $comment->post->id]
                        : null,
                    'links' => [
                        'self' => "https://api.example.com/comment/{$comment->id}/relationships/post",
                        'related' => "https://api.example.com/comment/{$comment->id}/post",
                    ]
                ]),
            ],
            'meta' => [
                'created_at' => $comment->created_at->format(DateTimeInterface::ATOM),
                'updated_at' => $comment->updated_at->format(DateTimeInterface::ATOM),
            ],
        ]);

        /** @var Collection $include */
        $include = $comments
            ->map(fn(Comment $comment) => collect()
                ->merge(
                    in_array('user', $relationships ?? [])
                        ? [UserResource::make($comment->user)->toArray($request)]
                        : []
                )
                ->merge(
                    in_array('post', $relationships ?? [])
                        ? [PostResource::make($comment->post)->toArray($request)]
                        : []
                ))
            ->reduce(fn(Collection $all, Collection $value) => $all->merge($value), collect());

        return collect(array_filter([
            'data' => $data,
            'included' => $include->uniqueStrict()->values()->all(),
            "links" => [
                "first" => "http://localhost/comment?page=1",
                "last" => "http://localhost/comment?page=10",
                "next" => "http://localhost/comment?page=2",
                "prev" => null
            ],
            "meta" => [
                'current_page' => 1,
                'from' => 1,
                'last_page' => 10,
                'links' => [
                    [
                        'active' => false,
                        'label' => "&laquo; Previous",
                        'url' => null,
                    ],
                    [
                        'active' => true,
                        'label' => '1',
                        'url' => "http://localhost/comment?page=1",
                    ],
                    ...(array_map(static fn($value) => [
                        'active' => false,
                        'label' => (string)$value,
                        'url' => "http://localhost/comment?page=$value",
                        ], range(2, 10))),
                    [
                        'active' => false,
                        'label' => "Next &raquo;",
                        'url' => "http://localhost/comment?page=2",
                    ],
                ],
                'path' => 'http://localhost/comment',
                'per_page' => 15,
                'to' => 15,
                'total' => 150,
            ],
        ]))
            ->toArray();
    }
}
