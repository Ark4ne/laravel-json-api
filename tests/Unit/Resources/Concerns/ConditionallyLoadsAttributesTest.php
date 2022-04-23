<?php

namespace Test\Unit\Resources\Concerns;

use Ark4ne\JsonApi\Resources\Concerns\ConditionallyLoadsAttributes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;
use Test\Support\Reflect;
use Test\TestCase;

class ConditionallyLoadsAttributesTest extends TestCase
{
    public function dataWhenInclude()
    {
        return [
            [false, 'test', []],
            [true, 'test', ['test']]
        ];
    }

    /**
     * @dataProvider dataWhenInclude
     */
    public function testWhenInclude($expected, $property, $query)
    {
        $request = new Request(['include' => implode(',', $query)]);

        $stub = new class(null) extends JsonResource {
            use ConditionallyLoadsAttributes;
        };

        $this->assertEquals(
            $expected ?: new MissingValue,
            Reflect::invoke($stub, 'whenIncluded', $request, $property, true)
        );
    }
}
