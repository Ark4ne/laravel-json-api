<?php

namespace Test\app\Http\Resources;

use Ark4ne\JsonApi\Resource\JsonApiResource;
use DateTimeInterface;
use Illuminate\Http\Request;

/**
 * @mixin \Test\App\Models\User
 */
class UserResource extends JsonApiResource
{
    protected function toAttributes(Request $request): iterable
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
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
            'posts' => fn() => PostResource::collection($this->posts)
                ->asRelationship([
                    'self' => "https://api.example.com/user/{$this->id}/relationships/posts",
                    'related' => "https://api.example.com/user/{$this->id}/posts",
                ]),
            'comments' => fn() => CommentResource::collection($this->whenLoaded('comments'))
                ->asRelationship([
                    'self' => "https://api.example.com/user/{$this->id}/relationships/comments",
                    'related' => "https://api.example.com/user/{$this->id}/comments",
                ])
        ];
    }
}
