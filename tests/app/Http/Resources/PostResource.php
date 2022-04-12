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
    protected function toAttributes(Request $request): iterable
    {
        return [
            'title' => $this->title,
            'content' => $this->content,
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
            'user' => fn() => UserResource::make($this->user)->asRelationship([
                'self' => "https://api.example.com/posts/{$this->id}/relationships/user",
            ]),
            'comments' => fn() => CommentResource::collection($this->comments)->asRelationship([
                'self' => "https://api.example.com/posts/{$this->id}/relationships/comments",
                'related' => "https://api.example.com/posts/{$this->id}/comments",
            ]),
        ];
    }
}
