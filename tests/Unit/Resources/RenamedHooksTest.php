<?php

declare(strict_types=1);

namespace Test\Unit\Resources;

use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Illuminate\Http\Request;
use Test\TestCase;
use Test\Unit\Resources\Fixtures\LegacyMetaCollection;
use Test\Unit\Resources\Fixtures\LegacyMetaResource;

/**
 * `toMeta()` of 1.x is renamed `toDocumentMeta()`: kept, it would be ignored silently, so it is refused.
 */
final class RenamedHooksTest extends TestCase
{
    public function test_a_resource_declaring_to_meta_cannot_be_compiled(): void
    {
        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('toDocumentMeta()');

        Definitions::compile(LegacyMetaResource::class);
    }

    public function test_a_collection_declaring_to_meta_throws_when_rendered(): void
    {
        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('toDocumentMeta()');

        (new LegacyMetaCollection([(object) ['id' => 1]]))->toResponse(new Request());
    }
}
