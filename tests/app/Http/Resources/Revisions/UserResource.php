<?php

declare(strict_types=1);

namespace Test\app\Http\Resources\Revisions;

use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Models\User;
use Test\app\Revisions\ApiRevision;

/**
 * The users of the revisions scenario: `display_name` ← `label` (`Labels`)
 * ← `name` (`Initial`), `born_at` a date-time ← a date (`Initial`), `full_name` until `Labels`; `posts` ← `post`, its first
 * post (`Initial`).
 *
 * @extends JsonApiResource<User>
 */
#[JsonApiType('users')]
final class UserResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'display_name' => Schema::string('name')->nullable()->whenHas()
                ->was(ApiRevision::Labels, name: 'label')
                ->was(ApiRevision::Initial, name: 'name'),
            'email' => Schema::string()->nullable()->whenHas(),
            'born_at' => Schema::dateTime('created_at')->nullable()->whenHas()
                // Converted by the base: the same instant, serialized as a date.
                ->was(ApiRevision::Initial, schema: Schema::date()),
            // Revisions
            'full_name' => Schema::string(fn() => "{$this->resource->name} <{$this->resource->email}>")
                ->until(ApiRevision::Labels),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'posts' => $this->many(PostResource::class)->whenIncluded()->was(
                ApiRevision::Initial,
                name: 'post',
                schema: $this->one(PostResource::class, fn() => $this->resource->posts->first())
                    ->withLoad('posts')
                    ->whenIncluded(),
            ),
            'comments' => $this->many(CommentResource::class)->whenIncluded(),
        ];
    }

    protected function toResourceMeta(Request $request): iterable
    {
        return [
            'updated_at' => Schema::dateTime()->nullable()->whenHas()->was(ApiRevision::Labels, name: 'modified_at'),
        ];
    }

    protected function toDocumentMeta(Request $request): iterable
    {
        return [
            'directory' => Schema::string(fn() => 'users')->was(ApiRevision::Labels, name: 'catalog'),
        ];
    }
}
