<?php

declare(strict_types=1);

namespace Test\Unit\Descriptors\Values;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\ValueType;
use Ark4ne\JsonApi\Descriptors\Values\ValueUlid;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;

final class ValueUlidTest extends TestCase
{
    public function test_a_ulid_object_is_rendered_as_a_string(): void
    {
        $ulid = Str::ulid();

        self::assertSame((string) $ulid, (new ValueUlid(null))->value($ulid, new Request(), Configuration::defaults()));
    }

    public function test_the_definition_is_a_string_of_format_ulid(): void
    {
        $definition = (new ValueUlid(null))->definition('id');

        self::assertSame(ValueType::String, $definition->type);
        self::assertSame('ulid', $definition->format);
    }
}
