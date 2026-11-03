<?php

declare(strict_types=1);

namespace Test\app\Http\Resources;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Models\Comment;

/**
 * @extends JsonApiResource<Comment>
 */
class CommentResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'content' => $this->string(),
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
                self: fn() => "https://api.example.com/comment/{$this->resource->id}/relationships/user",
                related: fn() => "https://api.example.com/comment/{$this->resource->id}/user",
            )->whenIncluded()->withLoad(true),
            'post' => $this->one()->links(
                self: fn() => "https://api.example.com/comment/{$this->resource->id}/relationships/post",
                related: fn() => "https://api.example.com/comment/{$this->resource->id}/post",
            )->whenIncluded()->withLoad(true),
        ];
    }
}
