<?php

namespace Test\Unit\Support;

use Ark4ne\JsonApi\Support\Values;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MergeValue;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Http\Resources\PotentiallyMissing;
use PHPUnit\Framework\Attributes\DataProvider;
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


    /**
     * Return a list of data to test hasAttribute method
     */
    public static function dataAttribute()
    {
        return [
            'array' => [['a' => 1], 'a', true, 1],
            'array.empty' => [[], 'a', false, null],
            'array.fail' => [['a' => 1], 'b', false, null],
            'object' => [(object)['a' => 1], 'a', true, 1],
            'object.empty' => [(object)[], 'a', false, null],
            'object.fail' => [(object)['a' => 1], 'b', false, null],
            'model' => [new class(['a' => 1]) extends \Illuminate\Database\Eloquent\Model {
                protected $fillable = ['a'];
            }, 'a', true, 1],
            'model.empty' => [new class() extends \Illuminate\Database\Eloquent\Model {
            }, 'a', false, null],
            'model.fail' => [new class(['a' => 1]) extends \Illuminate\Database\Eloquent\Model {
                protected $fillable = ['a'];
            }, 'b', false, null],
        ];
    }

    /**
     * @dataProvider dataAttribute
     */
    #[DataProvider('dataAttribute')]
    public function testHasAttribute($data, $attribute, $expected, $_ignored)
    {
        $this->assertEquals($expected, Values::hasAttribute($data, $attribute));
    }

    /**
     * @dataProvider dataAttribute
     */
    #[DataProvider('dataAttribute')]
    public function testGetAttribute($data, $attribute, $has, $expected)
    {
        $this->assertEquals($expected, Values::getAttribute($data, $attribute));
    }
}
