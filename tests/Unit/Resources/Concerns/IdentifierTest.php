<?php

declare(strict_types=1);

namespace Test\Unit\Resources\Concerns;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Test\Support\Reflect;
use Test\Support\Stub;
use Test\TestCase;

class IdentifierTest extends TestCase
{
    public function test_the_default_type_is_the_declared_one(): void
    {
        $stub = new class (null) extends JsonApiResource {};

        self::assertSame('anonymous', Reflect::invoke($stub, 'toType', new Request()));
    }

    public function test_the_type_hook_gives_the_type(): void
    {
        $stub = new class (null) extends JsonApiResource {
            protected function toType(Request $request): string
            {
                return 'my-type';
            }
        };

        self::assertSame('my-type', Reflect::invoke($stub, 'toType', new Request()));
    }

    public function test_the_default_identifier_is_the_key_of_the_model(): void
    {
        $stub = new class (Stub::model(['id' => 7])) extends JsonApiResource {};

        self::assertSame(7, Reflect::invoke($stub, 'toIdentifier', new Request()));
    }

    public function test_the_default_identifier_of_an_object_is_its_id(): void
    {
        $id = uniqid('id', true);

        $stub = new class ((object) ['id' => $id]) extends JsonApiResource {};

        self::assertSame($id, Reflect::invoke($stub, 'toIdentifier', new Request()));
    }

    public function test_the_identifier_hook_gives_the_identifier(): void
    {
        $id = uniqid('id', true);

        /** @extends JsonApiResource<object{id: string}> */
        $stub = new class ((object) ['id' => $id]) extends JsonApiResource {
            protected function toIdentifier(Request $request): string
            {
                return Str::afterLast($this->resource->id, '.');
            }
        };

        self::assertSame(Str::afterLast($id, '.'), Reflect::invoke($stub, 'toIdentifier', new Request()));
    }
}
