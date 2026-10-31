<?php

declare(strict_types=1);

namespace Test\Unit\Descriptors\Values;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\ValueType;
use Ark4ne\JsonApi\Descriptors\Values\ValueDateOnly;
use DateTimeImmutable;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class ValueDateOnlyTest extends TestCase
{
    public function test_a_date_is_rendered_without_its_time_whatever_the_configured_format(): void
    {
        $configuration = Configuration::fromArray(['describer' => ['date' => 'd/m/Y H:i']]);

        self::assertSame(
            '2026-10-30',
            (new ValueDateOnly(null))->value(new DateTimeImmutable('2026-10-30 14:30:00'), new Request(), $configuration),
        );
    }

    public function test_the_format_can_be_set(): void
    {
        self::assertSame(
            '30/10/2026',
            (new ValueDateOnly(null))->format('d/m/Y')->value('2026-10-30', new Request(), Configuration::defaults()),
        );
    }

    public function test_the_definition_is_a_date_of_format_y_m_d(): void
    {
        $definition = (new ValueDateOnly(null))->definition('birthday');

        self::assertSame(ValueType::Date, $definition->type);
        self::assertSame('Y-m-d', $definition->format);
    }
}
