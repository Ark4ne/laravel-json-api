<?php

namespace Test\Unit\Concerns;

use Ark4ne\JsonApi\Resource\Concerns\Meta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Test\Support\Reflect;
use Test\TestCase;

class MetaTest extends TestCase
{
    public function testToMeta()
    {
        $id = uniqid('id', true);

        $object = new class($id) {
            public $foo = 'bar';
            public $baz = 'tar';

            public function __construct(public $id) { }

            public function toArray() { return (array)$this; }
        };

        $stub = new class($object) extends JsonResource {
            use Meta;
        };

        $this->assertEquals(null, Reflect::invoke($stub, 'toMeta', new Request()));


        $stub = new class($object) extends JsonResource {
            use Meta;

            protected function toMeta(Request $request): ?iterable
            {
                return [
                    'copyright' => 'CC MyApp'
                ];
            }
        };

        $meta = [
            'copyright' => 'CC MyApp'
        ];

        $this->assertEquals($meta, Reflect::invoke($stub, 'toMeta', new Request()));
    }

    public function testToResourceMeta()
    {
        $id = uniqid('id', true);

        $object = new class($id) {
            public $foo = 'bar';
            public $baz = 'tar';

            public function __construct(public $id) { }

            public function toArray() { return (array)$this; }
        };

        $stub = new class($object) extends JsonResource {
            use Meta;
        };

        $this->assertEquals(null, Reflect::invoke($stub, 'toResourceMeta', new Request()));

        $stub = new class($object) extends JsonResource {
            use Meta;

            protected function toResourceMeta(Request $request): ?iterable
            {
                return [
                    'hash' => sha1($this->id)
                ];
            }
        };

        $meta = [
            'hash' => sha1($id)
        ];

        $this->assertEquals($meta, Reflect::invoke($stub, 'toResourceMeta', new Request()));
    }
}
