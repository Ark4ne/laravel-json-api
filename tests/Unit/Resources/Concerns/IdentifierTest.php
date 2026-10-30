<?php

declare(strict_types=1);

namespace Test\Unit\Resources\Concerns;

use Ark4ne\JsonApi\Resources\Concerns\Identifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use stdClass;
use Test\Support\Reflect;
use Test\TestCase;

class IdentifierTest extends TestCase
{
    public function testToType(): void
    {
        $stub = new class (collect()) extends JsonResource {
            use Identifier;
        };

        self::assertEquals('anonymous', Reflect::invoke($stub, 'toType', new Request()));

        $stub = new class (new JsonResource(null)) extends JsonResource {
            use Identifier;

            protected function toType(Request $request): string
            {
                return 'my-type';
            }
        };

        self::assertEquals(
            "my-type",
            Reflect::invoke($stub, 'toType', new Request()),
        );
    }

    public function testToIdentifier(): void
    {
        $id = uniqid('id', true);

        $object = new class ($id) extends stdClass {
            public $foo = 'bar';
            public $baz = 'tar';

            public function __construct(public $id) {}

            public function toArray()
            {
                return (array) $this;
            }
        };

        $stub = new class ($object) extends JsonResource {
            use Identifier;
        };

        self::assertEquals($id, Reflect::invoke($stub, 'toIdentifier', new Request()));

        $stub = new class ($object) extends JsonResource {
            use Identifier;

            protected function toIdentifier(Request $request): int|string
            {
                return Str::afterLast($this->resource->id, '-');
            }
        };

        self::assertEquals(
            Str::afterLast($id, '-'),
            Reflect::invoke($stub, 'toIdentifier', new Request()),
        );
    }
}
