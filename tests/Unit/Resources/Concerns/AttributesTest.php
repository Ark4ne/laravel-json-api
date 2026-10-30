<?php

declare(strict_types=1);

namespace Test\Unit\Resources\Concerns;

use Ark4ne\JsonApi\Resources\Concerns\Attributes;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use ArrayIterator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use stdClass;
use Test\Support\Reflect;
use Test\TestCase;

class AttributesTest extends TestCase
{
    public function testToAttributes(): void
    {
        $stub = fn($object) => new class ($object) extends JsonResource {
            use Attributes;

            protected function toType(Request $request)
            {
                return 'my-type';
            }
        };

        $object = new class extends stdClass {
            public $foo = 'bar';
            public $baz = 'tar';
            public function toArray()
            {
                return (array) $this;
            }
        };

        self::assertEquals(
            $object->toArray(),
            Reflect::invoke($stub($object), 'toAttributes', new Request()),
        );

        $array = [
            'test' => 'abc',
        ];

        self::assertEquals(
            $array,
            Reflect::invoke($stub($array), 'toAttributes', new Request()),
        );

        $iterable = new ArrayIterator($array);

        self::assertEquals(
            $array,
            Reflect::invoke($stub($iterable), 'toAttributes', new Request()),
        );

        $stdClass = (object) [];

        self::assertEquals(
            [],
            Reflect::invoke($stub($stdClass), 'toAttributes', new Request()),
        );
    }

    public function test_the_requested_attributes_are_rendered(): void
    {
        $object = new class extends stdClass {
            public $foo = 'bar';
            public $baz = 'tar';
            public function toArray()
            {
                return (array) $this;
            }
        };

        $stub = new class ($object) extends JsonApiResource {
            protected function toType(Request $request): string
            {
                return 'my-type';
            }

            protected function toIdentifier(Request $request): int
            {
                return 1;
            }

            public function toAttributes(Request $request): iterable
            {
                return collect([
                    'foo' => $this->foo,
                    'baz' => fn() => $this->baz,
                    'when_false' => $this->when(
                        false,
                        fn() => "when_false-{$this->foo}-{$this->baz}",
                    ),
                    'when_true' => $this->when(
                        true,
                        fn() => "when_true-{$this->foo}-{$this->baz}",
                    ),
                    $this->mergeWhen(false, fn() => [
                        'merged-false' => 'merged-false',
                    ]),
                    $this->mergeWhen(true, fn() => [
                        'merged-true' => 'merged-true',
                    ]),
                ]);
            }
        };

        $allAttributes = [
            'foo' => 'bar',
            'baz' => 'tar',
            'when_true' => "when_true-bar-tar",
            'merged-true' => 'merged-true',
        ];

        self::assertEquals(
            $allAttributes,
            $stub->toArray(new Request())['attributes'] ?? [],
        );

        self::assertEquals(
            array_intersect_key($allAttributes, array_fill_keys(['foo', 'baz'], true)),
            $stub->toArray(new Request(['fields' => ['my-type' => 'foo,baz']]))['attributes'] ?? [],
        );

        self::assertEquals(
            array_intersect_key($allAttributes, array_fill_keys(['foo', 'merged-true'], true)),
            $stub->toArray(new Request(['fields' => ['my-type' => 'foo,when_false,merged-true']]))['attributes'] ?? [],
        );

        self::assertArrayNotHasKey(
            'attributes',
            $stub->toArray(new Request(['fields' => ['my-type' => '']])),
        );
    }
}
