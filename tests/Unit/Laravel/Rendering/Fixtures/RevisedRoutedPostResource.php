<?php

declare(strict_types=1);

namespace Test\Unit\Laravel\Rendering\Fixtures;

use Ark4ne\JsonApi\Resources\Attributes\JsonApiRoutes;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Http\Resources\CommentResource;
use Test\app\Http\Resources\UserResource;
use Test\app\Revisions\ApiRevision;

/**
 * Routed relationships declaring revisions: `comments` ← `remarks` (`Initial`), `author` since `Labels`.
 *
 * @extends JsonApiResource<\Test\app\Models\Post>
 */
#[JsonApiRoutes(self: 'posts.show', relationship: 'posts.relationships', related: false)]
final class RevisedRoutedPostResource extends JsonApiResource
{
    /**
     * @return iterable<string, \Ark4ne\JsonApi\Descriptors\Relations\Relation<\Test\app\Models\Post>>
     */
    protected function toRelationships(Request $request): iterable
    {
        return [
            'comments' => $this->many(CommentResource::class)->was(ApiRevision::Initial, name: 'remarks'),
            'author' => $this->one(UserResource::class, 'user')->since(ApiRevision::Labels),
        ];
    }
}
