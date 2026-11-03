<?php

declare(strict_types=1);

namespace Test\Unit\Descriptors\Values;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\ValueType;
use Ark4ne\JsonApi\Descriptors\Values\ValueUuid;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;

final class ValueUuidTest extends TestCase
{
    public function test_a_uuid_object_or_string_is_rendered_as_a_string(): void
    {
        $uuid = Str::uuid();
        $value = new ValueUuid(null);

        self::assertSame($uuid->toString(), $value->value($uuid, new Request(), Configuration::defaults()));
        self::assertSame($uuid->toString(), $value->value($uuid->toString(), new Request(), Configuration::defaults()));
    }

    public function test_the_definition_is_a_string_of_format_uuid(): void
    {
        $definition = (new ValueUuid(null))->definition('id');

        self::assertSame(ValueType::String, $definition->type);
        self::assertSame('uuid', $definition->format);
    }
}
