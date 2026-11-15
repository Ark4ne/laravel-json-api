<?php

declare(strict_types=1);

namespace Test\app\Http\Resources\Revisions;

use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Models\Post;
use Test\app\Revisions\ApiRevision;

/**
 * The posts of the revisions scenario: their author is a `users` resource, a `writers` one up to `Labels`.
 *
 * @extends JsonApiResource<Post>
 */
#[JsonApiType('posts')]
final class PostResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'title' => Schema::string()->nullable()->whenHas(),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'user' => Schema::relationship($this->one(UserResource::class))
                ->was(ApiRevision::Labels, name: 'writer', schema: Schema::relationship($this->one(WriterResource::class))),
            'comments' => Schema::relationship($this->many(CommentResource::class)->whenIncluded()),
        ];
    }
}
