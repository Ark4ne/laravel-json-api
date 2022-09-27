<?php

namespace Test\app\Http\Resources;

use Ark4ne\JsonApi\Resources\JsonApiResource;
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
            'title' => $this->string(),
            'content' => $this->string()->whenInFields(),
        ];
    }

    protected function toResourceMeta(Request $request): ?iterable
    {
        return [
            $this->date('created_at')->format(DateTimeInterface::ATOM),
            $this->date('updated_at')->format(DateTimeInterface::ATOM),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'user' => $this->one(UserResource::class)->links(fn() => [
                'self' => "https://api.example.com/posts/{$this->resource->id}/relationships/user",
            ]),
            'comments' => $this->many(CommentResource::class)->links(fn() => [
                'self' => "https://api.example.com/posts/{$this->resource->id}/relationships/comments",
                'related' => "https://api.example.com/posts/{$this->resource->id}/comments",
            ])->meta(fn() => [
                'total' => $this->resource->comments()->getQuery()->count(),
            ]),
        ];
    }
}
