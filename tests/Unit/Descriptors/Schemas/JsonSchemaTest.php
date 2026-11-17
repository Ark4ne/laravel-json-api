<?php

declare(strict_types=1);

namespace Test\Unit\Descriptors\Schemas;

use Ark4ne\ApiRevisions\Definition\Kind;
use Ark4ne\ApiRevisions\Schema\InvalidValue;
use Ark4ne\JsonApi\Descriptors\Schemas\JsonSchema;
use PHPUnit\Framework\TestCase;

final class JsonSchemaTest extends TestCase
{
    public function test_a_json_string_is_decoded(): void
    {
        $json = new JsonSchema();

        self::assertSame(['a' => 1, 'b' => [true, null]], $json->normalize('{"a":1,"b":[true,null]}'));
        self::assertSame([1, 2], $json->normalize('[1,2]'));
        self::assertSame('text', $json->normalize('"text"'));
    }

    public function test_a_value_already_decoded_is_kept_as_it_is(): void
    {
        $json = new JsonSchema();

        self::assertSame(['a' => 1], $json->normalize(['a' => 1]));
        self::assertSame(12, $json->normalize(12));
    }

    public function test_a_string_that_is_not_json_is_refused(): void
    {
        $invalid = (new JsonSchema())->normalize('{not json');

        self::assertInstanceOf(InvalidValue::class, $invalid);
        self::assertSame(Kind::Custom, $invalid->expected);
        self::assertNull($invalid->cast);
    }

    public function test_a_null_document_is_refused_as_every_null(): void
    {
        self::assertInstanceOf(InvalidValue::class, (new JsonSchema())->normalize('null'));
        self::assertInstanceOf(InvalidValue::class, (new JsonSchema())->normalize(null));
    }

    public function test_the_type_is_a_custom_type_accepting_any_json(): void
    {
        $type = (new JsonSchema())->toType();

        self::assertSame(Kind::Custom, $type->kind);
        self::assertSame('json', $type->name);
        self::assertSame([], $type->fragment);
    }
}
