<?php

declare(strict_types=1);

namespace Test\Feature\Loading\Fixtures;

use Ark4ne\JsonApi\Resources\JsonApiCollection;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Models\Comment;
use Test\app\Models\Post;

/**
 * A feed, whose `items` are posts and comments (a polymorphic to-many relationship); not an Eloquent model.
 *
 * @extends JsonApiResource<object{id: int, items: mixed}>
 */
final class LinkageFeedResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'feed';
    }

    /**
     * @return iterable<string, \Ark4ne\JsonApi\Descriptors\Relations\Relation<mixed>>
     */
    protected function toRelationships(Request $request): iterable
    {
        return [
            'items' => $this->morphMany([
                Post::class => LinkagePostResource::class,
                Comment::class => LinkageCommentResource::class,
            ])->meta(static fn(mixed $items): array => [
                'collection' => $items instanceof JsonApiCollection,
                'count' => $items instanceof JsonApiCollection ? $items->count() : null,
            ]),
        ];
    }
}
