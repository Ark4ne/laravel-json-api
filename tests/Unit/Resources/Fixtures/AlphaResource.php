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
#[JsonApiType('alpha')]
final class AlphaResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'title' => $this->string(),
            'size' => $this->struct(['width' => $this->integer()], 'size'),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'beta' => $this->one(BetaResource::class, static fn(): null => null)->withLoad(['b']),
            'gammas' => $this->many(GammaResource::class, static fn(): array => [])->withLoad(['g']),
        ];
    }
}
