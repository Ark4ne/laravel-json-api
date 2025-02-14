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
            $this->applyWhen(fn () => true, [
                'with-apply-conditional-raw' => 'huge-data-set',
                'with-apply-conditional-closure' => fn () => 'huge-data-set',
                'with-apply-conditional-value' => $this->string(fn () => 'huge-data-set'),
            ]),
            'struct-set' => $this->struct(fn () => [
                $this->string('name'),
                'email' => $this->resource->email,
                'casted' => $this->string(fn() => 'string'),
                $this->applyWhen(fn () => true, [
                    'with-apply-conditional-raw' => 'huge-data-set',
                ]),
                'closure' => fn() => 'closure',
                'missing' => $this->mixed(fn() => 'value')->when(false),
                'sub-struct' => $this->struct(fn () => [
                    'int' => $this->float(fn () => 200),
                    'float' => $this->float(fn () => 1.1),
                ]),
                'third-struct' => $this->struct(fn () => [
                    'int' => $this->float(fn () => 300),
                    'float' => $this->float(fn () => 3.1),
                ])->when(false),
            ]),
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
