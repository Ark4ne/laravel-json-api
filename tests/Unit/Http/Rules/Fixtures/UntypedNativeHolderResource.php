<?php

declare(strict_types=1);

namespace Test\Unit\Http\Rules\Fixtures;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * @extends JsonApiResource<object>
 */
final class UntypedNativeHolderResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'holder';
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'untyped' => $this->one(UntypedNativeResource::class, static fn() => null),
        ];
    }
}
