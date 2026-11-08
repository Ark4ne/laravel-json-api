<?php

declare(strict_types=1);

namespace Test\app\Http\Resources\Revisions;

use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Models\Comment;
use Test\app\Revisions\ApiRevision;

/**
 * The comments of the revisions scenario: their author is a `users` resource, a `commenters` one up to `Labels`.
 *
 * @extends JsonApiResource<Comment>
 */
#[JsonApiType('comments')]
final class CommentResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'content' => $this->string(),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'user' => $this->one(UserResource::class)
                ->was(ApiRevision::Labels, name: 'commenter', value: $this->one(CommenterResource::class))
                ->whenIncluded(),
        ];
    }
}
