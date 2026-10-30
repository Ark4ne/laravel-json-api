<?php

declare(strict_types=1);

namespace Test\Unit\Resources\Fixtures;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * Still declares the `toMeta()` of 1.x, renamed `toDocumentMeta()`.
 *
 * @extends JsonApiResource<mixed>
 */
final class LegacyMetaResource extends JsonApiResource
{
    /**
     * @return array<string, string>
     */
    protected function toMeta(Request $request): array
    {
        return ['copyright' => 'ACME'];
    }
}
