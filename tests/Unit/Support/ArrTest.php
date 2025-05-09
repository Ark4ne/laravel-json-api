<?php

namespace Test\Unit\Support;

use Ark4ne\JsonApi\Support\Arr;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\TestCase;

class ArrTest extends TestCase
{
    public static function mergeProvider()
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
    #[DataProvider('mergeProvider')]
    public function testMerge($expected, $base, $with)
    {
        $this->assertEquals($expected, Arr::merge($base, $with));
    }

    public static function washProvider()
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
    #[DataProvider('washProvider')]
    public function testWash($expected, $base)
    {
        $this->assertEquals($expected, Arr::wash($base));
    }

    public static function toArrayProvider()
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
    #[DataProvider('toArrayProvider')]
    public function testToArray($expected, $base)
    {
        $this->assertEquals($expected, Arr::toArray($base));
    }

    public function testUndot()
    {
        $stub = [
            'test.a' => [
                '1' => 'test.a.1',
                '2' => [
                    '1' => 'test.a.2.1'
                ],
                '2.2' => 'test.a.2.2',
                '3' => 'test.a.3',
                '3.1' => [
                    '1' => 'test.a.3.1.1'
                ],
                '3.2' => 'test.a.3.2',
            ],
            'test' => 'test',
        ];

        $expected = [
            'test' => [
                '--saved--' => 'test',
                'a' => [
                    '1' => 'test.a.1',
                    '2' => [
                        '1' => 'test.a.2.1',
                        '2' => 'test.a.2.2',
                    ],
                    '3' => [
                        '--saved--' => 'test.a.3',
                        '1' => [
                            '1' => 'test.a.3.1.1'
                        ],
                        '2' => 'test.a.3.2',
                    ],
                ]
            ]
        ];

        $this->assertEquals($expected, Arr::undot($stub, '--saved--'));
    }

    public function testFlatDot() {

        $stub = [
            'test.a' => [
                '1' => 'test.a.1',
                '2' => [
                    '1' => 'test.a.2.1'
                ],
                '2.2' => 'test.a.2.2',
                '3' => 'test.a.3',
                '3.1' => [
                    '1' => 'test.a.3.1.1'
                ],
                '3.2' => 'test.a.3.2',
            ],
            'test' => 'test',
        ];

        $expected = [
            'test' => 'test',
            'test.a.1' => 'test.a.1',
            'test.a.2.1' => 'test.a.2.1',
            'test.a.2.2' => 'test.a.2.2',
            'test.a.3' => 'test.a.3',
            'test.a.3.1.1' => 'test.a.3.1.1',
            'test.a.3.2' => 'test.a.3.2',
        ];

        $this->assertEquals($expected, Arr::flatDot($stub));

        $expected = [
            'key.test' => 'test',
            'key.test.a.1' => 'test.a.1',
            'key.test.a.2.1' => 'test.a.2.1',
            'key.test.a.2.2' => 'test.a.2.2',
            'key.test.a.3' => 'test.a.3',
            'key.test.a.3.1.1' => 'test.a.3.1.1',
            'key.test.a.3.2' => 'test.a.3.2',
        ];

        $this->assertEquals($expected, Arr::flatDot($stub, 'key'));
    }
}
