<?php

declare(strict_types=1);

namespace Test\Unit\Descriptors\Values;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\ValueType;
use Ark4ne\JsonApi\Descriptors\Values\ValueDateTime;
use Ark4ne\JsonApi\Exceptions\RenderingException;
use Illuminate\Http\Request;
use Illuminate\Support\Stringable;
use PHPUnit\Framework\TestCase;

final class ValueDateTimeTest extends TestCase
{
    public function test_a_datetime_is_rendered_in_the_configured_format(): void
    {
        $configuration = Configuration::fromArray(['describer' => ['datetime' => 'd/m/Y H:i']]);

        self::assertSame('30/10/2026 14:30', (new ValueDateTime(null))->value('2026-10-30 14:30:00', new Request(), $configuration));
    }

    public function test_a_stringable_is_read_as_a_string(): void
    {
        self::assertSame(
            '2026-10-30T14:30:00+00:00',
            (new ValueDateTime(null))->value(new Stringable('2026-10-30 14:30:00+00:00'), new Request(), Configuration::defaults()),
        );
    }

    public function test_a_value_that_is_not_a_date_throws(): void
    {
        $this->expectException(RenderingException::class);
        $this->expectExceptionMessage('[array] is not a date');

        (new ValueDateTime(null))->value([], new Request(), Configuration::defaults());
    }

    public function test_the_definition_is_a_date_of_the_configured_format(): void
    {
        self::assertSame(ValueType::Date, (new ValueDateTime(null))->definition('at')->type);
    }
}
