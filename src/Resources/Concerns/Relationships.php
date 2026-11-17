<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Resources\Relationship;
use Illuminate\Http\Request;

/**
 * @template T The model of the resource
 */
trait Relationships
{
    use PrepareData;

    /**
     * @see https://jsonapi.org/format/#document-resource-object-relationships
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return iterable<array-key, mixed> Relationships by name (`$this->one()`, `$this->many()`, `$this->morph()`…, or
     *         those of a resource: `AvatarResource::relationship(fn() => …)`), and the groups of `applyWhen()`
     *
     * ```
     * return [
     *     'author' => $this->one(UserResource::class),
     *     'comments' => $this->many(CommentResource::class)->whenIncluded(),
     *     // read by a closure
     *     'avatar' => AvatarResource::relationship(fn() => $this->avatar),
     *     'posts' => PostResource::relationship(fn() => $this->posts)->asCollection(),
     * ];
     * ```
     */
    protected function toRelationships(Request $request): iterable
    {
        return [];
    }
}
