<?php

declare(strict_types=1);

namespace Test\Unit\Descriptors\Values;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\ValueType;
use Ark4ne\JsonApi\Descriptors\Values\ValueTime;
use DateTimeImmutable;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class ValueTimeTest extends TestCase
{
    public function test_a_time_is_rendered_without_its_date(): void
    {
        $value = new ValueTime(null);

        self::assertSame('14:30:00', $value->value(new DateTimeImmutable('2026-10-30 14:30:00'), new Request(), Configuration::defaults()));
        self::assertSame('14:30:00', $value->value('14:30', new Request(), Configuration::defaults()));
    }

    public function test_the_definition_is_a_date_of_format_h_i_s(): void
    {
        $definition = (new ValueTime(null))->definition('opens-at');

        self::assertSame(ValueType::Date, $definition->type);
        self::assertSame('H:i:s', $definition->format);
    }
}
