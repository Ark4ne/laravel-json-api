<?php

declare(strict_types=1);

namespace Test\Unit\Loading\Fixtures;

use Ark4ne\JsonApi\Configuration\Linkage;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * One relationship per linkage, each read from the relation of its name.
 *
 * @extends JsonApiResource<mixed>
 */
#[JsonApiType('delta')]
final class DeltaResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'always' => $this->one(self::class)->linkage(Linkage::Always),
            'loaded' => $this->one(self::class)->linkage(Linkage::Loaded),
            'never' => $this->one(self::class)->linkage(Linkage::Included),
        ];
    }
}
