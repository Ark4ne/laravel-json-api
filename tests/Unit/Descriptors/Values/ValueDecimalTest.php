<?php

declare(strict_types=1);

namespace Test\Unit\Descriptors\Values;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\ValueType;
use Ark4ne\JsonApi\Descriptors\Values\ValueDecimal;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValueDecimalTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function decimals(): iterable
    {
        yield 'a decimal column' => ['1234.50', '1234.50'];
        yield 'an integer' => [1234, '1234'];
        yield 'a float, shortest writing' => [0.1, '0.1'];
        yield 'an integral float' => [2.0, '2'];
        yield 'a large float, digit by digit' => [1e21, '1000000000000000000000'];
        yield 'leading zeros' => ['00012.30', '12.30'];
        yield 'beyond the precision of a float' => ['12345678901234567890.123456789', '12345678901234567890.123456789'];
        yield 'not a number' => ['abc', '0'];
    }

    #[DataProvider('decimals')]
    public function test_a_decimal_is_rendered_as_a_string_without_a_float_on_the_way(mixed $of, string $expected): void
    {
        self::assertSame($expected, (new ValueDecimal(null))->value($of, new Request(), Configuration::defaults()));
    }

    /**
     * @return iterable<string, array{mixed, int, string}>
     */
    public static function scaled(): iterable
    {
        yield 'padded' => ['12.3', 2, '12.30'];
        yield 'rounded half away from zero' => ['1.235', 2, '1.24'];
        yield 'negative rounded away from zero' => ['-1.235', 2, '-1.24'];
        yield 'carried over' => ['9.995', 2, '10.00'];
        yield 'no decimals' => ['2.5', 0, '3'];
        yield 'negative zero' => ['-0.004', 2, '0.00'];
    }

    #[DataProvider('scaled')]
    public function test_the_scale_sets_the_number_of_decimals(mixed $of, int $scale, string $expected): void
    {
        self::assertSame(
            $expected,
            (new ValueDecimal(null))->scale($scale)->value($of, new Request(), Configuration::defaults()),
        );
    }

    public function test_the_definition_is_a_string_of_format_decimal(): void
    {
        $definition = (new ValueDecimal(null))->definition('price');

        self::assertSame(ValueType::String, $definition->type);
        self::assertSame('decimal', $definition->format);
    }
}
