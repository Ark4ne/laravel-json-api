<?php

namespace Test\app\Http\Resources;

use Ark4ne\JsonApi\Resource\JsonApiResource;
use DateTimeInterface;
use Illuminate\Http\Request;

/**
 * @extends JsonApiResource<\Test\app\Models\Post>
 */
class PostResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'post';
    }

    protected function toAttributes(Request $request): iterable
    {
        return [
            'title' => fn() => $this->resource->title,
            'content' => fn() => $this->resource->content,
        ];
    }

    protected function toResourceMeta(Request $request): ?iterable
    {
        return [
            'created_at' => $this->resource->created_at->format(DateTimeInterface::ATOM),
            'updated_at' => $this->resource->updated_at->format(DateTimeInterface::ATOM),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'user' => UserResource::relationship(fn() => $this->resource->user, fn() => [
                'self' => "https://api.example.com/posts/{$this->resource->id}/relationships/user",
            ]),
            'comments' => CommentResource::relationship(fn() => $this->resource->comments, fn() => [
                'self' => "https://api.example.com/posts/{$this->resource->id}/relationships/comments",
                'related' => "https://api.example.com/posts/{$this->resource->id}/comments",
            ])->asCollection(),
        ];
    }
}
