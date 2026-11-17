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
#[JsonApiType('beta')]
final class BetaResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return ['name' => $this->string()];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'gamma' => $this->one(GammaResource::class, static fn(): null => null)->withLoad(['g', 'g.a']),
        ];
    }
}
