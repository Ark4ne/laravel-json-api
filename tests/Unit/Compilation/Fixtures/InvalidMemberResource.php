<?php

declare(strict_types=1);

namespace Test\Unit\Compilation\Fixtures;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use RuntimeException;
use stdClass;
use Test\app\Http\Resources\UserResource;

/**
 * One invalid member among valid ones.
 *
 * @extends JsonApiResource<\Test\app\Models\Post>
 */
final class InvalidMemberResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'invalid-member';
    }

    public function toAttributes(Request $request): iterable
    {
        return [
            'title' => $this->string(),
            'broken' => $this->struct(static fn() => throw new RuntimeException('Needs a real model.')),
            'content' => $this->string(),
        ];
    }

    /**
     * @return iterable<string, \Ark4ne\JsonApi\Descriptors\Relations\Relation<\Test\app\Models\Post>>
     */
    protected function toRelationships(Request $request): iterable
    {
        return [
            'author' => $this->one(UserResource::class),
            'other' => $this->one(stdClass::class),
        ];
    }
}
