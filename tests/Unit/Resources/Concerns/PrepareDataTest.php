<?php

namespace Test\Unit\Resources\Concerns;

use Ark4ne\JsonApi\Resources\Concerns\PrepareData;
use Illuminate\Http\Resources\MergeValue;
use Illuminate\Http\Resources\MissingValue;
use Test\Support\Reflect;
use Test\TestCase;

class PrepareDataTest extends TestCase
{
    public function testMergeValues()
    {
        $stub = new class {
            use PrepareData;
        };

        $actual = Reflect::invoke($stub, 'mergeValues', []);
        $this->assertEquals([], $actual);
        $actual = Reflect::invoke($stub, 'mergeValues', collect());
        $this->assertEquals([], $actual);

        $expected = $sample = [
            'a' => 'abc',
            'b' => 123,
            'c' => true,
            'd' => false,
            'e' => null,
            'f' => new MissingValue,
        ];
        $actual = Reflect::invoke($stub, 'mergeValues', $sample);
        $this->assertEquals($expected, $actual);
        $actual = Reflect::invoke($stub, 'mergeValues', collect($sample));
        $this->assertEquals($expected, $actual);

        $sampleWithMerge = $sample;
        $sampleWithMerge[] = new MergeValue($sample);

        $actual = Reflect::invoke($stub, 'mergeValues', $sampleWithMerge);
        $this->assertEquals($expected, $actual);

        $actual = Reflect::invoke($stub, 'mergeValues', $sample + [
                new MergeValue([
                    'g' => '12345',
                    'h' => [
                        'i' => 'def',
                        new MergeValue([new MergeValue([true])]),
                        new MergeValue([[new MergeValue([[true]])]])
                    ],
                    'k' => [
                        new MergeValue([1, 2, 3]),
                        new MergeValue([4, 5, 6])
                    ],
                    new MergeValue([
                        'self' => new MissingValue()
                    ]),
                    new MergeValue([
                        'self' => new MissingValue()
                    ]),
                    new MergeValue([
                        'self' => 'three'
                    ]),
                    new MergeValue([
                        'self' => new MissingValue()
                    ]),
                ]),
            ]
        );
        $this->assertEquals($sample + [
                'g' => '12345',
                'h' => [
                    'i' => 'def',
                    true,
                    [[true]],
                ],
                'k' => [
                    1,
                    2,
                    3,
                    4,
                    5,
                    6
                ],
                'self' => 'three',
            ], $actual);
    }
}
