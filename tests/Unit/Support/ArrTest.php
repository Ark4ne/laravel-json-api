<?php

namespace Test\Unit\Support;

use Ark4ne\JsonApi\Support\Arr;
use Test\TestCase;

class ArrTest extends TestCase
{
    public function mergeProvider()
    {
        return [
            // [expected, base, with]
            [[], [], []],
            [['foo' => []], ['foo' => []], []],
            [['foo' => []], [], ['foo' => []]],
            [['foo' => []], ['foo' => []], ['foo' => []]],
            [['foo' => ['bar']], ['foo' => ['bar']], ['foo' => ['bar']]],
            [['foo' => ['bar', 'baz']], ['foo' => ['bar']], ['foo' => ['bar', 'baz']]],
            [
                ['foo' => ['bar' => ['baz' => [1, 2]]]],
                ['foo' => ['bar' => ['baz' => [1]]]],
                ['foo' => ['bar' => ['baz' => [2]]]]
            ],
            [
                [
                    'included' => [
                        ['id' => 1, 'attributes' => ['a' => 1]],
                        ['id' => 2, 'attributes' => ['a' => 2]]
                    ]
                ],
                [
                    'included' => [
                        ['id' => 1, 'attributes' => ['a' => 1]],
                    ]
                ],
                [
                    'included' => [
                        ['id' => 2, 'attributes' => ['a' => 2]]
                    ]
                ]
            ],
            [
                [
                    'included' => [
                        ['id' => 1, 'attributes' => ['a' => 1]],
                    ]
                ],
                [
                    'included' => [
                        ['id' => 1, 'attributes' => ['a' => 1]],
                    ]
                ],
                [
                    'included' => [
                        ['id' => 1, 'attributes' => ['a' => 1]]
                    ]
                ]
            ]
        ];
    }

    /**
     * @dataProvider mergeProvider
     */
    public function testMerge($expected, $base, $with)
    {
        $this->assertEquals($expected, Arr::merge($base, $with));
    }

    public function washProvider()
    {
        return [
            // [expected, with]
            [[], []],
            [[], ['foo' => []]],
            [['foo' => [1]], ['foo' => [1, 1, 1]]],
            [['foo' => ['a' => 1, 'b' => 1]], ['foo' => ['a' => 1, 'b' => 1]]],
            [
                [
                    'included' => [['id' => 1, 'attributes' => []]]
                ],
                [
                    'included' => [['id' => 1, 'attributes' => []]],
                    'meta' => []
                ],
            ],
        ];
    }

    /**
     * @dataProvider washProvider
     */
    public function testWash($expected, $base)
    {
        $this->assertEquals($expected, Arr::wash($base));
    }

    public function toArrayProvider()
    {
        return [
            // [expected, with]
            [[], []],
            [[], collect()],
            [['foo' => 'bar'], collect(['foo' => 'bar'])],
            [['foo' => ['foo' => 'bar']], collect(['foo' => collect(['foo' => 'bar'])])],
            [
                ['foo' => ['foo' => ['foo' => ['foo' => 'bar']]]],
                collect(['foo' => ['foo' => collect(['foo' => ['foo' => 'bar']])]])
            ],
            [
                ['foo' => ['foo' => ['foo' => ['foo' => 'bar']]]],
                ['foo' => ['foo' => collect(['foo' => ['foo' => 'bar']])]]
            ],
        ];
    }

    /**
     * @dataProvider toArrayProvider
     */
    public function testToArray($expected, $base)
    {
        $this->assertEquals($expected, Arr::toArray($base));
    }
}
