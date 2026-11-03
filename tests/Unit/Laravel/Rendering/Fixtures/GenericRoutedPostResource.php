<?php

declare(strict_types=1);

namespace Test\Unit\Laravel\Rendering\Fixtures;

use Ark4ne\JsonApi\Resources\Attributes\JsonApiRoutes;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Http\Resources\CommentResource;
use Test\app\Http\Resources\UserResource;

/**
 * One relationship route for every relationship, `posts/{post}/relationships/{relationship}`.
 *
 * @extends JsonApiResource<\Test\app\Models\Post>
 */
#[JsonApiRoutes(self: 'posts.show', relationship: 'posts.relationships', related: false)]
final class GenericRoutedPostResource extends JsonApiResource
{
    /**
     * @return iterable<string, \Ark4ne\JsonApi\Descriptors\Relations\Relation<\Test\app\Models\Post>>
     */
    protected function toRelationships(Request $request): iterable
    {
        return [
            'comments' => $this->many(CommentResource::class),
            'author' => $this->one(UserResource::class, 'user')->when(static fn() => false),
        ];
    }
}
