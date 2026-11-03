<?php

declare(strict_types=1);

namespace Test\Feature\Loading\Fixtures;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Models\Comment;
use Test\app\Models\Post;

/**
 * A like, whose `likeable` is a post or a comment.
 *
 * @extends JsonApiResource<\Test\app\Models\Like>
 */
final class LinkageLikeResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'like';
    }

    /**
     * @return iterable<string, \Ark4ne\JsonApi\Descriptors\Relations\Relation<\Test\app\Models\Like>>
     */
    protected function toRelationships(Request $request): iterable
    {
        return [
            'user' => $this->one(LinkageUserResource::class),
            'likeable' => $this->morph([
                Post::class => LinkagePostResource::class,
                Comment::class => LinkageCommentResource::class,
            ]),
        ];
    }
}
