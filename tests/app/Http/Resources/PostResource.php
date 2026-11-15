<?php

declare(strict_types=1);

namespace Test\app\Http\Resources;

use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Enums\State;
use Test\app\Models\Post;

/**
 * @extends JsonApiResource<Post>
 */
class PostResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'state' => Schema::enum(State::class)->nullable()->whenHas(),
            'title' => Schema::string()->nullable()->whenHas(),
            'content' => Schema::string()->nullable()->whenInFields(),
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
            'user' => Schema::relationship($this->one()->links(
                self: fn() => "https://api.example.com/posts/{$this->resource->id}/relationships/user",
            )->withLoad(true)),
            'comments' => Schema::relationship($this->many()->links(
                self: fn() => "https://api.example.com/posts/{$this->resource->id}/relationships/comments",
                related: fn() => "https://api.example.com/posts/{$this->resource->id}/comments",
            )->meta(fn() => [
                'total' => $this->resource->relationLoaded('comments')
                    ? $this->resource->comments->count()
                    : $this->resource->comments()->toBase()->count(),
            ])->withLoad('comments')),
        ];
    }
}
