<?php

declare(strict_types=1);

namespace Test\Feature\Links\Fixtures;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Http\Resources\CommentResource;
use Test\app\Http\Resources\UserResource;

/**
 * @extends JsonApiResource<\Test\app\Models\Post>
 */
final class LinkedPostResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return ['title' => $this->string()];
    }

    /**
     * @return iterable<string, \Ark4ne\JsonApi\Descriptors\Relations\Relation<\Test\app\Models\Post>|\Ark4ne\JsonApi\Resources\Relationship<CommentResource>>
     */
    protected function toRelationships(Request $request): iterable
    {
        return [
            'user' => $this->one(UserResource::class)->links(
                self: fn() => "https://api.example.com/posts/{$this->resource->id}/relationships/user",
                related: 'https://api.example.com/users',
            ),
            'comments' => $this->many(CommentResource::class)->links(
                fn() => ['self' => 'from-closure', 'related' => 'https://api.example.com/comments'],
                self: fn() => "https://api.example.com/posts/{$this->resource->id}/relationships/comments",
                related: fn() => null,
            ),
            'replies' => CommentResource::relationship(fn() => $this->resource->comments)
                ->asCollection()
                ->links(related: fn() => "https://api.example.com/posts/{$this->resource->id}/comments"),
        ];
    }
}
