<?php

namespace Test\Unit\Support;

use Ark4ne\JsonApi\Support\Values;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MergeValue;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Http\Resources\PotentiallyMissing;
use Test\TestCase;

class ValuesTest extends TestCase
{
    public function testMergeValues()
    {
        $actual = Values::mergeValues([]);
        $this->assertEquals([], $actual);
        $actual = Values::mergeValues(collect());
        $this->assertEquals([], $actual);

        $expected = $sample = [
            'a' => 'abc',
            'b' => 123,
            'c' => true,
            'd' => false,
            'e' => null,
            'f' => new MissingValue,
        ];
        $actual = Values::mergeValues($sample);
        $this->assertEquals($expected, $actual);
        $actual = Values::mergeValues(collect($sample));
        $this->assertEquals($expected, $actual);

        $sampleWithMerge = $sample;
        $sampleWithMerge[] = new MergeValue($sample);

        $actual = Values::mergeValues($sampleWithMerge);
        $this->assertEquals($expected, $actual);

        $actual = Values::mergeValues($sample + [
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

    public function testIsMissing()
    {
        $maybeMissing = fn(bool $missing) => new class($missing) implements PotentiallyMissing {
            public function __construct(protected bool $missing)
            {
            }

            public function isMissing()
            {
                return $this->missing;
            }
        };

        $this->assertEquals(false, Values::isMissing(true));
        $this->assertEquals(false, Values::isMissing(false));
        $this->assertEquals(false, Values::isMissing(null));

        $this->assertEquals(true, Values::isMissing(new MissingValue()));
        $this->assertEquals(true, Values::isMissing($maybeMissing(true)));
        $this->assertEquals(false, Values::isMissing($maybeMissing(false)));

        $this->assertEquals(true, Values::isMissing(new JsonResource(new MissingValue())));
    }
}
