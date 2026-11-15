<?php

declare(strict_types=1);

namespace Test\Unit\Descriptors\Schemas;

use Ark4ne\ApiRevisions\Definition\Kind;
use Ark4ne\ApiRevisions\Schema\InvalidValue;
use Ark4ne\JsonApi\Descriptors\Schemas\DecimalSchema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class DecimalSchemaTest extends TestCase
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
        yield 'an exponent' => ['1e3', '1000'];
    }

    #[DataProvider('decimals')]
    public function test_a_decimal_is_normalized_to_a_string_without_a_float_on_the_way(mixed $of, string $expected): void
    {
        $decimal = new DecimalSchema();

        self::assertSame($expected, $decimal->normalize($of));
        self::assertSame($expected, $decimal->serialize($decimal->normalize($of)));
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
        yield 'a negative scale is none' => ['2.5', -1, '3'];
    }

    #[DataProvider('scaled')]
    public function test_the_scale_sets_the_number_of_decimals(mixed $of, int $scale, string $expected): void
    {
        self::assertSame($expected, (new DecimalSchema())->scale($scale)->normalize($of));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function refused(): iterable
    {
        yield 'not a number' => ['abc'];
        yield 'a boolean' => [true];
        yield 'an object' => [new stdClass()];
        yield 'an infinite float' => [INF];
        yield 'not a float' => [NAN];
    }

    #[DataProvider('refused')]
    public function test_a_value_that_is_not_a_number_is_refused_and_cast_to_zero(mixed $of): void
    {
        $invalid = (new DecimalSchema())->scale(2)->normalize($of);

        self::assertInstanceOf(InvalidValue::class, $invalid);
        self::assertSame(Kind::Custom, $invalid->expected);
        self::assertSame('0.00', $invalid->cast);
    }

    public function test_the_scale_returns_a_copy(): void
    {
        $decimal = new DecimalSchema();
        $scaled = $decimal->scale(2);

        self::assertNotSame($decimal, $scaled);
        self::assertSame('1.5', $decimal->normalize('1.5'));
    }

    public function test_the_type_is_a_custom_string_of_format_decimal(): void
    {
        $type = (new DecimalSchema())->toType();

        self::assertSame(Kind::Custom, $type->kind);
        self::assertSame('decimal', $type->name);
        self::assertSame(['type' => 'string', 'format' => 'decimal', 'pattern' => '^-?\d+(\.\d+)?$'], $type->fragment);
    }

    public function test_the_pattern_of_the_type_follows_the_scale(): void
    {
        self::assertSame('^-?\d+\.\d{2}$', (new DecimalSchema())->scale(2)->toType()->fragment['pattern']);
        self::assertSame('^-?\d+$', (new DecimalSchema())->scale(0)->toType()->fragment['pattern']);
    }
}
