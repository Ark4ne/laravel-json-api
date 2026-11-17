<?php

declare(strict_types=1);

namespace Test\Unit\Descriptors\Schemas;

use Ark4ne\ApiRevisions\Definition\Kind;
use Ark4ne\ApiRevisions\Schema\InvalidValue;
use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\JsonApi\Descriptors\Schemas\FloatSchema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class FloatSchemaTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed}>
     */
    public static function values(): iterable
    {
        yield 'a float' => [1.239];
        yield 'an int' => [3];
        yield 'a numeric string' => ['1.5'];
        yield 'a string with blanks' => [' 1.5'];
        yield 'a word' => ['abc'];
        yield 'infinity' => [INF];
        yield 'an object' => [new stdClass()];
        yield 'a bool' => [true];
    }

    #[DataProvider('values')]
    public function test_without_precision_it_reads_a_value_as_the_float_of_the_base_does(mixed $of): void
    {
        $expected = Schema::float()->normalize($of);
        $actual = (new FloatSchema())->normalize($of);

        if ($expected instanceof InvalidValue) {
            self::assertInstanceOf(InvalidValue::class, $actual);
            self::assertSame($expected->cast, $actual->cast);
            self::assertSame($expected->expected, $actual->expected);
        } else {
            self::assertSame($expected, $actual);
        }
    }

    public function test_it_is_the_float_of_the_base_for_the_tools(): void
    {
        $type = (new FloatSchema())->min(0)->max(5.5)->precision(1)->toType();

        self::assertSame(Kind::Float, $type->kind);
        self::assertSame(0, $type->min);
        self::assertSame(5.5, $type->max);
        self::assertEquals(Schema::float()->min(0)->max(5.5)->toType(), $type);
    }

    /**
     * @return iterable<string, array{mixed, int, float}>
     */
    public static function rounded(): iterable
    {
        yield 'rounded up' => [1.239, 2, 1.24];
        yield 'rounded down' => [1.234, 2, 1.23];
        yield 'half away from zero' => [1.15, 1, 1.2];
        yield 'negative, away from zero' => [-1.239, 2, -1.24];
        yield 'no decimals' => [2.5, 0, 3.0];
        yield 'a numeric string' => ['4.29', 1, 4.3];
        yield 'an int' => [4, 2, 4.0];
        yield 'tens' => [1234.5, -1, 1230.0];
    }

    #[DataProvider('rounded')]
    public function test_a_precision_rounds_the_value(mixed $of, int $precision, float $expected): void
    {
        $float = (new FloatSchema())->precision($precision);

        self::assertSame($precision, $float->rounding());
        self::assertSame($expected, $float->normalize($of));
    }

    /**
     * @return iterable<string, array{float, int, float}>
     */
    public static function truncated(): iterable
    {
        yield 'towards zero' => [1.239, 2, 1.23];
        yield 'negative, towards zero' => [-1.239, 2, -1.23];
        yield 'a step lost in the binary writing is restored' => [1.15, 2, 1.15];
        yield 'no decimals' => [2.9, 0, 2.0];
    }

    #[DataProvider('truncated')]
    public function test_truncate_cuts_at_the_precision(float $of, int $precision, float $expected): void
    {
        self::assertSame($expected, (new FloatSchema())->precision($precision)->truncate()->normalize($of));
    }

    public function test_truncate_without_precision_leaves_the_value_as_it_is(): void
    {
        self::assertSame(1.239, (new FloatSchema())->truncate()->normalize(1.239));
    }

    public function test_a_precision_can_be_removed(): void
    {
        $float = (new FloatSchema())->precision(1)->precision(null);

        self::assertNull($float->rounding());
        self::assertSame(1.239, $float->normalize(1.239));
    }

    public function test_a_value_that_is_refused_is_still_refused_with_a_precision(): void
    {
        $refused = (new FloatSchema())->precision(1)->normalize('abc');

        self::assertInstanceOf(InvalidValue::class, $refused);
    }

    public function test_it_is_immutable(): void
    {
        $float = new FloatSchema();
        $float->precision(1)->truncate();

        self::assertNull($float->rounding());
        self::assertSame(1.239, $float->normalize(1.239));
    }
}
