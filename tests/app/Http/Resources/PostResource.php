<?php

namespace Test\app\Http\Resources;

use Ark4ne\JsonApi\Resource\JsonApiResource;
use DateTimeInterface;
use Illuminate\Http\Request;

/**
 * @mixin \Test\app\Models\Post
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
            'title' => fn() => $this->title,
            'content' => fn() => $this->content,
        ];
    }

    protected function toResourceMeta(Request $request): ?iterable
    {
        return [
            'created_at' => $this->created_at->format(DateTimeInterface::ATOM),
            'updated_at' => $this->updated_at->format(DateTimeInterface::ATOM),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'user' => UserResource::relationship(fn() => $this->user, fn() => [
                'self' => "https://api.example.com/posts/{$this->id}/relationships/user",
            ]),
            'comments' => CommentResource::relationship(fn() => $this->comments, fn() => [
                'self' => "https://api.example.com/posts/{$this->id}/relationships/comments",
                'related' => "https://api.example.com/posts/{$this->id}/comments",
            ])->asCollection(),
        ];
    }
}
