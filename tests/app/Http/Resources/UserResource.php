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
    /** @var \Test\app\Models\User $resource */
    public $resource;

    protected function toType(Request $request): string
    {
        return 'user';
    }

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
            'posts' => PostResource::relationship(fn() => $this->posts, fn() => [
                'self' => "https://api.example.com/user/{$this->id}/relationships/posts",
                'related' => "https://api.example.com/user/{$this->id}/posts",
            ])->asCollection(),
            'comments' => CommentResource
                ::relationship(fn() => $this->whenLoaded('comments'))
                ->withLinks(fn() => [
                    'self' => "https://api.example.com/user/{$this->id}/relationships/comments",
                    'related' => "https://api.example.com/user/{$this->id}/comments",
                ])
                ->asCollection()
        ];
    }
}
