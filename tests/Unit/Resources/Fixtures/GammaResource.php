<?php

declare(strict_types=1);

namespace Test\Unit\Resources\Fixtures;

use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * One of the cycle alpha → beta → gamma → alpha.
 *
 * @extends JsonApiResource<mixed>
 */
#[JsonApiType('gamma')]
final class GammaResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return ['label' => $this->string()];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'alpha' => $this->one(AlphaResource::class, static fn(): null => null)->withLoad(['a']),
            'lazy' => $this->many(AlphaResource::class, static fn(): array => []),
        ];
    }
}
