<?php

namespace Test\Unit\Support;

use Ark4ne\JsonApi\Resource\Support\With;
use Test\TestCase;

class WithTest extends TestCase
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
        $this->assertEquals($expected, With::merge($base, $with));
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
        $this->assertEquals($expected, With::wash($base));
    }
}
