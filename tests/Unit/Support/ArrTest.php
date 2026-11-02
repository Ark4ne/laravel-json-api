<?php

declare(strict_types=1);

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
                ['foo' => ['bar' => ['baz' => [2]]]],
            ],
            [
                [
                    'included' => [
                        ['id' => 1, 'attributes' => ['a' => 1]],
                        ['id' => 2, 'attributes' => ['a' => 2]],
                    ],
                ],
                [
                    'included' => [
                        ['id' => 1, 'attributes' => ['a' => 1]],
                    ],
                ],
                [
                    'included' => [
                        ['id' => 2, 'attributes' => ['a' => 2]],
                    ],
                ],
            ],
            [
                [
                    'included' => [
                        ['id' => 1, 'attributes' => ['a' => 1]],
                    ],
                ],
                [
                    'included' => [
                        ['id' => 1, 'attributes' => ['a' => 1]],
                    ],
                ],
                [
                    'included' => [
                        ['id' => 1, 'attributes' => ['a' => 1]],
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider mergeProvider
     */
    #[DataProvider('mergeProvider')]
    public function test_merge_merges_nested_arrays(mixed $expected, mixed $base, mixed $with): void
    {
        self::assertEquals($expected, Arr::merge($base, $with));
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
                    'included' => [['id' => 1, 'attributes' => []]],
                ],
                [
                    'included' => [['id' => 1, 'attributes' => []]],
                    'meta' => [],
                ],
            ],
        ];
    }

    /**
     * @dataProvider washProvider
     */
    #[DataProvider('washProvider')]
    public function test_wash_removes_empty_values_recursively(mixed $expected, mixed $base): void
    {
        self::assertEquals($expected, Arr::wash($base));
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
                collect(['foo' => ['foo' => collect(['foo' => ['foo' => 'bar']])]]),
            ],
            [
                ['foo' => ['foo' => ['foo' => ['foo' => 'bar']]]],
                ['foo' => ['foo' => collect(['foo' => ['foo' => 'bar']])]],
            ],
        ];
    }

    /**
     * @dataProvider toArrayProvider
     */
    #[DataProvider('toArrayProvider')]
    public function test_to_array_converts_nested_arrayables(mixed $expected, mixed $base): void
    {
        self::assertEquals($expected, Arr::toArray($base));
    }

    public function test_undot_expands_dotted_keys_and_saves_values_overwritten(): void
    {
        $stub = [
            'test.a' => [
                '1' => 'test.a.1',
                '2' => [
                    '1' => 'test.a.2.1',
                ],
                '2.2' => 'test.a.2.2',
                '3' => 'test.a.3',
                '3.1' => [
                    '1' => 'test.a.3.1.1',
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
                            '1' => 'test.a.3.1.1',
                        ],
                        '2' => 'test.a.3.2',
                    ],
                ],
            ],
        ];

        self::assertEquals($expected, Arr::undot($stub, '--saved--'));
    }

    public function test_flat_dot_flattens_to_dotted_keys_with_an_optional_prefix(): void
    {

        $stub = [
            'test.a' => [
                '1' => 'test.a.1',
                '2' => [
                    '1' => 'test.a.2.1',
                ],
                '2.2' => 'test.a.2.2',
                '3' => 'test.a.3',
                '3.1' => [
                    '1' => 'test.a.3.1.1',
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

        self::assertEquals($expected, Arr::flatDot($stub));

        $expected = [
            'key.test' => 'test',
            'key.test.a.1' => 'test.a.1',
            'key.test.a.2.1' => 'test.a.2.1',
            'key.test.a.2.2' => 'test.a.2.2',
            'key.test.a.3' => 'test.a.3',
            'key.test.a.3.1.1' => 'test.a.3.1.1',
            'key.test.a.3.2' => 'test.a.3.2',
        ];

        self::assertEquals($expected, Arr::flatDot($stub, 'key'));
    }
}
