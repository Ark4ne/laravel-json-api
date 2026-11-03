<?php

declare(strict_types=1);

namespace Test\app\Http\Resources;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * A post of the package whose user and comments are resources of Laravel's native JSON:API: `writer` and `notes` read
 * them again, with the meta of their identifiers, `notes` through a collection of native resources.
 *
 * @extends JsonApiResource<\Test\app\Models\Post>
 */
final class NativePostResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'post';
    }

    public function toAttributes(Request $request): iterable
    {
        return [
            'title' => $this->string(),
        ];
    }

    /**
     * @return iterable<string, \Ark4ne\JsonApi\Descriptors\Relations\Relation<\Test\app\Models\Post>>
     */
    protected function toRelationships(Request $request): iterable
    {
        return [
            'user' => $this->one(NativeUserResource::class),
            'comments' => $this->many(NativeCommentResource::class),
            'writer' => $this->one(NativeUserResource::class, 'user')
                ->identifierMeta(static fn(NativeUserResource $user) => ['name' => $user->resource->name]),
            'notes' => $this->many(NativeCommentCollection::class, 'comments')
                ->identifierMeta(static fn(NativeCommentResource $comment) => ['resource' => $comment::class]),
        ];
    }
}
