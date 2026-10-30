<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Resources\Relationship;
use Illuminate\Http\Request;

trait Relationships
{
    use PrepareData;

    /**
     * @see https://jsonapi.org/format/#document-resource-object-relationships
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return iterable<string, \Ark4ne\JsonApi\Descriptors\Relations\Relation|Relationship|\Illuminate\Http\Resources\PotentiallyMissing>|iterable<array-key, \Ark4ne\JsonApi\Descriptors\Relations\Relation|\Illuminate\Http\Resources\MergeValue|\Illuminate\Http\Resources\MissingValue>
     *
     * ```
     * return [
     *     'avatar' => AvatarResource::relationship(fn() => $this->avatar),
     *     // as collection
     *     'posts' => PostResource::relationship(fn() => $this->posts)->asCollection(),
     *     // with laravel conditional relationships
     *     'comments' => CommentResource::relationship(fn() => $this->when($this->canComments(), fn() => $this->comments))->asCollection(),
     * ];
     * ```
     */
    protected function toRelationships(Request $request): iterable
    {
        return [];
    }
}
