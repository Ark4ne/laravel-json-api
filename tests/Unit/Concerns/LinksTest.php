<?php

namespace Test\Unit\Concerns;

use Ark4ne\JsonApi\Resource\Concerns\Links;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Test\Support\Reflect;
use Test\TestCase;

class LinksTest extends TestCase
{
    public function testToLinks()
    {
        $id = uniqid('id', true);

        $object = new class($id) {
            public $foo = 'bar';
            public $baz = 'tar';

            public function __construct(public $id) { }

            public function toArray() { return (array)$this; }
        };

        $stub = new class($object) extends JsonResource {
            use Links;
        };

        $this->assertEquals(null, Reflect::invoke($stub, 'toLinks', new Request()));

        $stub = new class($object) extends JsonResource {
            use Links;

            protected function toLinks(Request $request): ?iterable
            {
                return [
                    'self' => "://api.com/my-type/{$this->id}"
                ];
            }
        };

        $links = [
            'self' => "://api.com/my-type/{$id}"
        ];

        $this->assertEquals($links, Reflect::invoke($stub, 'toLinks', new Request()));
    }
}
