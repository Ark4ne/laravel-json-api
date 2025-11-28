<?php

namespace Test\Unit\Descriptors;

use Ark4ne\JsonApi\Descriptors\Values\ValueArray;
use Ark4ne\JsonApi\Descriptors\Values\ValueString;
use Ark4ne\JsonApi\Descriptors\Values\ValueInteger;
use Ark4ne\JsonApi\Descriptors\Values\ValueEnum;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class TypedArrayTest extends TestCase
{
    private Request $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new Request();
    }

    public function testArrayOfStrings()
    {
        $array = new ValueArray('tags');
        $array->of(new ValueString(null));

        $input = ['php', 'laravel', 'json-api', true, 1.0];
        $result = $array->value($input, $this->request);

        $this->assertIsArray($result);
        $this->assertCount(5, $result);
        $this->assertSame(['php', 'laravel', 'json-api', '1', '1'], $result);
    }

    public function testArrayOfIntegers()
    {
        $array = new ValueArray('scores');
        $array->of(new ValueInteger(null));

        $input = [1.5, '2', 3, '4.9'];
        $result = $array->value($input, $this->request);

        $this->assertIsArray($result);
        $this->assertCount(4, $result);
        $this->assertSame([1, 2, 3, 4], $result);
    }

    public function testArrayOfEnums()
    {
        $array = new ValueArray('statuses');
        $array->of(new ValueEnum(null));

        $input = [
            Status::ACTIVE,
            Status::INACTIVE,
            Status::PENDING
        ];
        $result = $array->value($input, $this->request);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertSame([1, 0, 2], $result); // Assuming backed enum values
    }

    public function testNestedArrays()
    {
        $innerArray = new ValueArray(null);
        $innerArray->of(new ValueInteger(null));

        $outerArray = new ValueArray('matrix');
        $outerArray->of($innerArray);

        $input = [
            ['1', 2, 3],
            [4, '5', 6],
            [7, 8, '9']
        ];
        $result = $outerArray->value($input, $this->request);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertSame([
            [1, 2, 3],
            [4, 5, 6],
            [7, 8, 9]
        ], $result);
    }

    public function testEmptyArray()
    {
        $array = new ValueArray('tags');
        $array->of(new ValueString(null));

        $result = $array->value([], $this->request);

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testArrayWithoutTypeSpecification()
    {
        $array = new ValueArray('mixed');

        $input = [1, 'string', true, 3.14];
        $result = $array->value($input, $this->request);

        $this->assertIsArray($result);
        $this->assertCount(4, $result);
        $this->assertSame([1, 'string', true, 3.14], $result);
    }
}

// Example enum for testing
enum Status: int
{
    case INACTIVE = 0;
    case ACTIVE = 1;
    case PENDING = 2;
}