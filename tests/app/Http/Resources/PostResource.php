<?php

declare(strict_types=1);

namespace Test\app\Http\Resources;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Models\Post;

/**
 * @extends JsonApiResource<Post>
 */
class PostResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'state' => $this->enum(),
            'title' => $this->string(),
            'content' => $this->string()->whenInFields(),
        ];
    }

    protected function toResourceMeta(Request $request): ?iterable
    {
        return [
            $this->datetime('created_at'),
            $this->datetime('updated_at'),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'user' => $this->one()->links(
                self: fn() => "https://api.example.com/posts/{$this->resource->id}/relationships/user",
            )->withLoad(true),
            'comments' => $this->many()->links(
                self: fn() => "https://api.example.com/posts/{$this->resource->id}/relationships/comments",
                related: fn() => "https://api.example.com/posts/{$this->resource->id}/comments",
            )->meta(fn() => [
                'total' => $this->resource->relationLoaded('comments')
                    ? $this->resource->comments->count()
                    : $this->resource->comments()->toBase()->count(),
            ])->withLoad('comments'),
        ];
    }
}
