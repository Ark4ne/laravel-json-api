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
            'posts' => $this->many(PostResource::class)->links(fn() => [
                'self' => "https://api.example.com/user/{$this->resource->id}/relationships/posts",
                'related' => "https://api.example.com/user/{$this->resource->id}/posts",
            ]),

            'comments' => $this->many(CommentResource::class)
                ->whenLoaded()
                ->links(fn() => [
                    'self' => "https://api.example.com/user/{$this->resource->id}/relationships/comments",
                    'related' => "https://api.example.com/user/{$this->resource->id}/comments",
                ]),
        ];
    }
}
