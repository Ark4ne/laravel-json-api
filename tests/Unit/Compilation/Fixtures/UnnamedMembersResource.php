<?php

declare(strict_types=1);

namespace Test\Unit\Compilation\Fixtures;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Http\Resources\UserResource;

/**
 * Members declared without a key: named after the attribute or the relation they read.
 *
 * @extends JsonApiResource<\Test\app\Models\Post>
 */
final class UnnamedMembersResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'unnamed-members';
    }

    public function toAttributes(Request $request): iterable
    {
        return [
            $this->string('title'),
        ];
    }

    /**
     * @return iterable<array-key, \Ark4ne\JsonApi\Descriptors\Relations\Relation<mixed>>
     */
    protected function toRelationships(Request $request): iterable
    {
        return [
            $this->one(UserResource::class, 'author'),
        ];
    }
}
