<?php

declare(strict_types=1);

namespace Test\Unit\Compilation\Fixtures;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Http\Resources\UserResource;

/**
 * @extends JsonApiResource<\Test\app\Models\Post>
 */
final class ReservedNameResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'reserved-name';
    }

    public function toAttributes(Request $request): iterable
    {
        return [
            'id' => $this->integer(),
            'title' => $this->string(),
        ];
    }

    /**
     * @return iterable<string, \Ark4ne\JsonApi\Descriptors\Relations\Relation<\Test\app\Models\Post>>
     */
    protected function toRelationships(Request $request): iterable
    {
        return [
            'type' => $this->one(UserResource::class),
        ];
    }
}
