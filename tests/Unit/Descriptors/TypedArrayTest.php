<?php

declare(strict_types=1);

namespace Test\Unit\Descriptors;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Descriptors\Values\ValueArray;
use Ark4ne\JsonApi\Descriptors\Values\ValueEnum;
use Ark4ne\JsonApi\Descriptors\Values\ValueInteger;
use Ark4ne\JsonApi\Descriptors\Values\ValueString;
use Ark4ne\JsonApi\Descriptors\Values\ValueStruct;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Test\app\Values\MoneyValue;

class TypedArrayTest extends TestCase
{
    private Request $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new Request();
    }

    public function test_an_array_of_strings_casts_each_item_to_a_string(): void
    {
        $array = new ValueArray('tags');
        $array = $array->of(new ValueString(null));

        $input = ['php', 'laravel', 'json-api', true, 1.0];
        $result = $array->value($input, $this->request, Configuration::defaults());

        self::assertIsArray($result);
        self::assertCount(5, $result);
        self::assertSame(['php', 'laravel', 'json-api', '1', '1'], $result);
    }

    public function test_an_array_of_integers_casts_each_item_to_an_integer(): void
    {
        $array = new ValueArray('scores');
        $array = $array->of(new ValueInteger(null));

        $input = [1.5, '2', 3, '4.9'];
        $result = $array->value($input, $this->request, Configuration::defaults());

        self::assertIsArray($result);
        self::assertCount(4, $result);
        self::assertSame([1, 2, 3, 4], $result);
    }

    public function test_an_array_of_enums_renders_the_value_of_each_case(): void
    {
        $array = new ValueArray('statuses');
        $array = $array->of(new ValueEnum(null));

        $input = [
            Status::ACTIVE,
            Status::INACTIVE,
            Status::PENDING,
        ];
        $result = $array->value($input, $this->request, Configuration::defaults());

        self::assertIsArray($result);
        self::assertCount(3, $result);
        self::assertSame([1, 0, 2], $result); // Assuming backed enum values
    }

    public function test_an_array_of_arrays_casts_the_items_of_the_inner_arrays(): void
    {
        $innerArray = new ValueArray(null);
        $innerArray = $innerArray->of(new ValueInteger(null));

        $outerArray = new ValueArray('matrix');
        $outerArray = $outerArray->of($innerArray);

        $input = [
            ['1', 2, 3],
            [4, '5', 6],
            [7, 8, '9'],
        ];
        $result = $outerArray->value($input, $this->request, Configuration::defaults());

        self::assertIsArray($result);
        self::assertCount(3, $result);
        self::assertSame([
            [1, 2, 3],
            [4, 5, 6],
            [7, 8, 9],
        ], $result);
    }

    public function test_an_empty_typed_array_renders_an_empty_list(): void
    {
        $array = new ValueArray('tags');
        $array = $array->of(new ValueString(null));

        $result = $array->value([], $this->request, Configuration::defaults());

        self::assertIsArray($result);
        self::assertCount(0, $result);
    }

    public function test_an_untyped_array_renders_its_items_as_they_are(): void
    {
        $array = new ValueArray('mixed');

        $input = [1, 'string', true, 3.14];
        $result = $array->value($input, $this->request, Configuration::defaults());

        self::assertIsArray($result);
        self::assertCount(4, $result);
        self::assertSame([1, 'string', true, 3.14], $result);
    }

    public function test_the_item_type_is_named_by_its_type(): void
    {
        $configuration = Configuration::fromArray(['describer' => ['types' => ['money' => MoneyValue::class]]]);
        $prices = (new ValueArray(null))->of('money');

        self::assertSame(['1.50', '0.99'], $prices->value([150, 99], new Request(), $configuration));
        self::assertSame('money', $prices->definition('prices', null, $configuration)->items?->format);
        self::assertSame([1, 2], (new ValueArray(null))->of('integer')->value(['1', '2'], new Request(), $configuration));
    }

    public function test_a_struct_given_by_its_class_throws_when_declared(): void
    {
        $this->expectException(DefinitionException::class);

        (new ValueArray(null))->of(ValueStruct::class);
    }
}

// Example enum for testing
enum Status: int
{
    case INACTIVE = 0;
    case ACTIVE = 1;
    case PENDING = 2;

}
