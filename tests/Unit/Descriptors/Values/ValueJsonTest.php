<?php

declare(strict_types=1);

namespace Test\Unit\Descriptors\Values;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\ValueType;
use Ark4ne\JsonApi\Descriptors\Values\ValueJson;
use Ark4ne\JsonApi\Exceptions\RenderingException;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class ValueJsonTest extends TestCase
{
    public function test_a_json_string_is_rendered_decoded(): void
    {
        self::assertSame(
            ['theme' => 'dark', 'tags' => ['a']],
            (new ValueJson(null))->value('{"theme":"dark","tags":["a"]}', new Request(), Configuration::defaults()),
        );
    }

    public function test_a_decoded_value_is_rendered_as_it_is(): void
    {
        self::assertSame(['theme' => 'dark'], (new ValueJson(null))->value(['theme' => 'dark'], new Request(), Configuration::defaults()));
    }

    public function test_an_invalid_json_string_throws_naming_the_attribute(): void
    {
        $this->expectException(RenderingException::class);
        $this->expectExceptionMessage('[settings]');

        (new ValueJson('settings'))->value('{theme', new Request(), Configuration::defaults());
    }

    public function test_the_definition_is_mixed_of_format_json(): void
    {
        $definition = (new ValueJson(null))->definition('settings');

        self::assertSame(ValueType::Mixed, $definition->type);
        self::assertSame('json', $definition->format);
    }
}
