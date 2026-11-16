<?php

declare(strict_types=1);

namespace Test\app\Http\Resources;

use Ark4ne\ApiRevisions\Schema\Schema;
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
            'content' => Schema::string()->nullable()->whenHas(),
        ];
    }

    protected function toResourceMeta(Request $request): ?iterable
    {
        return [
            'created_at' => Schema::dateTime()->nullable()->whenHas(),
            'updated_at' => Schema::dateTime()->nullable()->whenHas(),
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
