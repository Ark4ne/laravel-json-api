<?php

declare(strict_types=1);

namespace Test\app\Schemas;

use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Http\Resources\Revisions\CommentResource;
use Test\app\Http\Resources\Revisions\UserResource;
use Test\app\Http\Resources\Revisions\WriterResource;
use Test\app\Models\Post;
use Test\app\Revisions\ApiRevision;

/**
 * The posts of the revisions scenario (`Http\Resources\Revisions\PostResource`) declared with the descriptors of
 * `ark4ne/laravel-api-revisions`: the same documents at every revision. `user` ← `writer`, to the `writers` type
 * (`Labels`).
 *
 * @extends JsonApiResource<Post>
 */
#[JsonApiType('posts')]
final class PostResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'title' => Schema::string(),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'user' => $this->one(UserResource::class)
                ->was(ApiRevision::Labels, name: 'writer', schema: $this->one(WriterResource::class)),
            'comments' => $this->many(CommentResource::class)->whenIncluded(),
        ];
    }
}
