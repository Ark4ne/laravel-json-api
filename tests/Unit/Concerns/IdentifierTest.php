<?php

namespace Test\Unit\Concerns;

use Ark4ne\JsonApi\Resource\Concerns\Identifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Test\Support\Reflect;
use Test\TestCase;

class IdentifierTest extends TestCase
{
    public function testToType()
    {
        $stub = new class(collect()) extends JsonResource {
            use Identifier;
        };

        $this->assertEquals("collection", Reflect::invoke($stub, 'toType', new Request()));

        $stub = new class(new JsonResource(null)) extends JsonResource {
            use Identifier;
        };

        $this->assertEquals("json-resource", Reflect::invoke($stub, 'toType', new Request()));

        $stub = new class(new JsonResource(null)) extends JsonResource {
            use Identifier;

            protected function toType(Request $request): string
            {
                return 'my-type';
            }
        };

        $this->assertEquals(
            "my-type",
            Reflect::invoke($stub, 'toType', new Request())
        );
    }

    public function testToIdentifier()
    {
        $id = uniqid('id', true);

        $object = new class($id) {
            public $foo = 'bar';
            public $baz = 'tar';

            public function __construct(public $id) { }

            public function toArray() { return (array)$this; }
        };

        $stub = new class($object) extends JsonResource {
            use Identifier;
        };

        $this->assertEquals($id, Reflect::invoke($stub, 'toIdentifier', new Request()));

        $stub = new class($object) extends JsonResource {
            use Identifier;

            protected function toIdentifier(Request $request): int|string
            {
                return Str::afterLast($this->resource->id, '-');
            }
        };

        $this->assertEquals(
            Str::afterLast($id, '-'),
            Reflect::invoke($stub, 'toIdentifier', new Request())
        );
    }
}
