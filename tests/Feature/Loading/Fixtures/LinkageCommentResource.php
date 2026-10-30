<?php

declare(strict_types=1);

namespace Test\Feature\Loading\Fixtures;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * A comment whose post renders only when included, and whose `writer` is read from `nothing`, which is not a relation.
 *
 * @extends JsonApiResource<\Test\app\Models\Comment>
 */
final class LinkageCommentResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'comment';
    }

    public function toAttributes(Request $request): iterable
    {
        return [
            'content' => $this->string(),
        ];
    }

    /**
     * @return iterable<string, \Ark4ne\JsonApi\Descriptors\Relations\Relation<mixed>>
     */
    protected function toRelationships(Request $request): iterable
    {
        return [
            'user' => $this->one(LinkageUserResource::class),
            'post' => $this->one(LinkagePostResource::class)->whenIncluded(),
            'writer' => $this->one(LinkageUserResource::class, 'nothing'),
        ];
    }
}
