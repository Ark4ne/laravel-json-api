<?php

declare(strict_types=1);

namespace Test\Unit\Compilation\Fixtures;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Http\Resources\UserResource;

/**
 * @extends JsonApiResource<\Test\app\Models\Post>
 */
final class MergeWhenResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'merge-when';
    }

    public function toAttributes(Request $request): iterable
    {
        return [
            'title' => $this->string(),
            $this->mergeWhen(false, [
                'hidden' => $this->string(),
            ]),
        ];
    }

    /**
     * Laravel types mergeWhen() as mixed.
     *
     * @return iterable<array-key, mixed>
     */
    protected function toRelationships(Request $request): iterable // @phpstan-ignore method.childReturnType
    {
        return [
            $this->mergeWhen(false, [
                'author' => $this->one(UserResource::class),
            ]),
        ];
    }
}
