<?php

namespace Test\Unit\Resources\Concerns;

use Ark4ne\JsonApi\Resources\Concerns\Attributes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Test\Support\Reflect;
use Test\TestCase;

class AttributesTest extends TestCase
{
    public function testToAttributes()
    {
        $object = new class {
            public $foo = 'bar';
            public $baz = 'tar';
            public function toArray() { return (array)$this; }
        };

        $stub = new class($object) extends JsonResource {
            use Attributes;

            protected function toType(Request $request)
            {
                return 'my-type';
            }
        };

        $this->assertEquals(
            $object->toArray(),
            Reflect::invoke($stub, 'toAttributes', new Request())
        );
    }

    public function testRequestedAttributes()
    {
        $object = new class {
            public $foo = 'bar';
            public $baz = 'tar';
            public function toArray() { return (array)$this; }
        };

        $stub = new class($object) extends JsonResource {
            use Attributes;

            protected function toType(Request $request)
            {
                return 'my-type';
            }

            protected function toAttributes(Request $request): iterable
            {
                return collect([
                    'foo' => $this->foo,
                    'baz' => fn() => $this->baz,
                    'when_false' => $this->when(false,
                        fn() => "when_false-{$this->foo}-{$this->baz}"),
                    'when_true' => $this->when(true,
                        fn() => "when_true-{$this->foo}-{$this->baz}"),
                    $this->mergeWhen(false, fn() => [
                        'merged-false' => 'merged-false'
                    ]),
                    $this->mergeWhen(true, fn() => [
                        'merged-true' => 'merged-true'
                    ]),
                ]);
            }
        };

        $allAttributes = [
            'foo' => 'bar',
            'baz' => 'tar',
            'when_true' => "when_true-bar-tar",
            'merged-true' => 'merged-true'
        ];

        $this->assertEquals(
            $allAttributes,
            Reflect::invoke($stub, 'requestedAttributes', new Request())
        );

        $this->assertEquals(
            array_intersect_key($allAttributes, array_fill_keys(['foo', 'baz'], true)),
            Reflect::invoke($stub, 'requestedAttributes', new Request(['fields' => ['my-type' => 'foo,baz']]))
        );

        $this->assertEquals(
            array_intersect_key($allAttributes, array_fill_keys(['foo', 'merged-true'], true)),
            Reflect::invoke($stub, 'requestedAttributes',
                new Request(['fields' => ['my-type' => 'foo,when_false,merged-true']]))
        );

        $this->assertEquals(
            [],
            Reflect::invoke($stub, 'requestedAttributes', new Request(['fields' => ['my-type' => '']]))
        );
    }
}
