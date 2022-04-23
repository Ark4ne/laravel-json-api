<?php

namespace Test\app\Http\Resources;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use DateTimeInterface;
use Illuminate\Http\Request;

/**
 * @extends JsonApiResource<\Test\app\Models\Comment>
 */
class CommentResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'comment';
    }

    protected function toAttributes(Request $request): iterable
    {
        return [
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
            'user' => UserResource::relationship(fn() => $this->resource->user)
                ->withLinks(fn() => [
                    'self' => "https://api.example.com/comment/{$this->resource->id}/relationships/user",
                    'related' => "https://api.example.com/comment/{$this->resource->id}/user",
                ])
                ->whenIncluded(),
            'post' => PostResource::relationship(fn() => $this->resource->post)
                ->withLinks(fn() => [
                    'self' => "https://api.example.com/comment/{$this->resource->id}/relationships/post",
                    'related' => "https://api.example.com/comment/{$this->resource->id}/post",
                ])
                ->whenIncluded(),
        ];
    }
}
