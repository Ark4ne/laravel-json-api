<?php

namespace Test\app\Http\Resources;

use Ark4ne\JsonApi\Resource\JsonApiResource;
use DateTimeInterface;
use Illuminate\Http\Request;

/**
 * @extends JsonApiResource<\Test\app\Models\User>
 */
class UserResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'user';
    }

    protected function toAttributes(Request $request): iterable
    {
        return [
            'name' => $this->resource->name,
            'email' => $this->resource->email,
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
            'posts' => PostResource::relationship(fn() => $this->resource->posts, fn() => [
                'self' => "https://api.example.com/user/{$this->resource->id}/relationships/posts",
                'related' => "https://api.example.com/user/{$this->resource->id}/posts",
            ])->asCollection(),
            'comments' => CommentResource::relationship(fn() => $this->whenLoaded('comments'), fn() => [
                'self' => "https://api.example.com/user/{$this->resource->id}/relationships/comments",
                'related' => "https://api.example.com/user/{$this->resource->id}/comments",
            ])
            ->asCollection()
        ];
    }
}
