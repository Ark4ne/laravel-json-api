<?php

declare(strict_types=1);

namespace Benchmark\Package;

use Ark4ne\JsonApi\Descriptors\Relations\RelationOne;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * @extends JsonApiResource<\Benchmark\Models\Comment>
 */
final class CommentResource extends JsonApiResource
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
     * @return iterable<string, RelationOne<mixed>>
     */
    protected function toRelationships(Request $request): iterable
    {
        return [
            'user' => $this->one(UserResource::class)->withLoad(true),
        ];
    }
}
