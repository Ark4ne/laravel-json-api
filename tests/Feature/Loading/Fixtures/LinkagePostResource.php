<?php

declare(strict_types=1);

namespace Test\Feature\Loading\Fixtures;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * A post whose relationships are read from the relations of their name: loaded implicitly.
 *
 * @extends JsonApiResource<\Test\app\Models\Post>
 */
final class LinkagePostResource extends JsonApiResource
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
     * @return iterable<string, \Ark4ne\JsonApi\Descriptors\Relations\Relation<mixed>>
     */
    protected function toRelationships(Request $request): iterable
    {
        return [
            'user' => $this->one(LinkageUserResource::class),
            'comments' => $this->many(LinkageCommentResource::class),
        ];
    }
}
