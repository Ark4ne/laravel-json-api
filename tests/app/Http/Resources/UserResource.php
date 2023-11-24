<?php

namespace Test\app\Http\Resources;

use Ark4ne\JsonApi\Resources\Concerns\ConditionallyLoadsAttributes;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use DateTimeInterface;
use Illuminate\Http\Request;

/**
 * @extends JsonApiResource<\Test\app\Models\User>
 */
class UserResource extends JsonApiResource
{
    use ConditionallyLoadsAttributes;

    protected function toType(Request $request): string
    {
        return 'user';
    }

    protected function toAttributes(Request $request): iterable
    {
        return [
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'only-with-fields' => $this->string(fn() => 'huge-data-set')->whenInFields(),
        ];
    }

    protected function toResourceMeta(Request $request): ?iterable
    {
        return [
            'created_at' => $this->date()->format(DateTimeInterface::ATOM),
            'updated_at' => $this->date()->format(DateTimeInterface::ATOM),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'main-post' => $this->one(PostResource::class, fn() => $this->resource->post)
                ->withLoad('post'),
            'posts' => PostResource::relationship(fn() => $this->resource->posts, fn() => [
                'self' => "https://api.example.com/user/{$this->resource->id}/relationships/posts",
                'related' => "https://api.example.com/user/{$this->resource->id}/posts",
            ], fn() => [
                'total' => $this->resource->posts()->getQuery()->count(),
            ])
                ->asCollection()
                ->withLoad(true),

            'comments' => $this->many(CommentResource::class)
                ->whenLoaded()
                ->links(fn() => [
                    'self' => "https://api.example.com/user/{$this->resource->id}/relationships/comments",
                    'related' => "https://api.example.com/user/{$this->resource->id}/comments",
                ])
                ->meta(fn() => [
                    'total' => $this->resource->comments()->getQuery()->count(),
                ])
                ->withLoad(['comments' => fn($q) => $q->where('content', 'like', '%e%')])
        ];
    }
}
