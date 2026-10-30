<?php

declare(strict_types=1);

namespace Test\Unit\Resources\Fixtures;

use Ark4ne\JsonApi\Resources\JsonApiCollection;
use Illuminate\Http\Request;

/**
 * Still declares the `toMeta()` of 1.x, renamed `toDocumentMeta()`.
 *
 * @extends JsonApiCollection<\Test\Unit\Compilation\Fixtures\BlogPostResource>
 */
final class LegacyMetaCollection extends JsonApiCollection
{
    public $collects = \Test\Unit\Compilation\Fixtures\BlogPostResource::class;

    /**
     * @return array<string, string>
     */
    protected function toMeta(Request $request): array
    {
        return ['copyright' => 'ACME'];
    }
}
