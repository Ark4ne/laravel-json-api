<?php

declare(strict_types=1);

namespace Test\Feature\Links\Fixtures;

use Ark4ne\JsonApi\Resources\Attributes\JsonApiRoutes;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Http\Resources\CommentResource;
use Test\app\Http\Resources\UserResource;

/**
 * @extends JsonApiResource<\Test\app\Models\Post>
 */
#[JsonApiRoutes(self: 'posts.show')]
final class RoutedPostResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return ['title' => $this->string()];
    }

    /**
     * @return iterable<string, \Ark4ne\JsonApi\Descriptors\Relations\Relation<\Test\app\Models\Post>>
     */
    protected function toRelationships(Request $request): iterable
    {
        return [
            'user' => $this->one(UserResource::class),
            'comments' => $this->many(CommentResource::class),
            'tags' => $this->many(CommentResource::class, 'comments')->links(self: 'https://declared.example.com'),
        ];
    }
}
