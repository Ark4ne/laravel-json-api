<?php

declare(strict_types=1);

namespace Test\Unit\Resources\Concerns;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\Support\Reflect;
use Test\TestCase;

class MetaTest extends TestCase
{
    public function test_a_resource_has_no_meta_by_default(): void
    {
        $stub = new class ((object) ['id' => 1]) extends JsonApiResource {};

        self::assertNull(Reflect::invoke($stub, 'toDocumentMeta', new Request()));
        self::assertNull(Reflect::invoke($stub, 'toResourceMeta', new Request()));
    }

    public function test_the_document_meta_hook_gives_the_document_meta(): void
    {
        $stub = new class ((object) ['id' => 1]) extends JsonApiResource {
            /**
             * @return array<string, string>
             */
            protected function toDocumentMeta(Request $request): array
            {
                return ['copyright' => 'CC MyApp'];
            }
        };

        self::assertSame(['copyright' => 'CC MyApp'], Reflect::invoke($stub, 'toDocumentMeta', new Request()));
    }

    public function test_the_resource_meta_hook_gives_the_resource_meta(): void
    {
        $id = uniqid('id', true);

        $stub = new class ((object) ['id' => $id]) extends JsonApiResource {
            /**
             * @return array<string, string>
             */
            protected function toResourceMeta(Request $request): array
            {
                return ['hash' => sha1((string) $this->toIdentifier($request))];
            }
        };

        self::assertSame(['hash' => sha1($id)], Reflect::invoke($stub, 'toResourceMeta', new Request()));
    }
}
