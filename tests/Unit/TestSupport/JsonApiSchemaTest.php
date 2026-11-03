<?php

declare(strict_types=1);

namespace Test\Unit\TestSupport;

use PHPUnit\Framework\TestCase;
use Test\Support\JsonApiSchema;

final class JsonApiSchemaTest extends TestCase
{
    public function test_a_compliant_document_has_no_error(): void
    {
        self::assertSame([], JsonApiSchema::errors([
            'data' => [
                'type' => 'user',
                'id' => '1',
                'attributes' => ['name' => 'Ada'],
                'relationships' => [
                    'boss' => ['data' => null],
                    'friends' => ['data' => [['type' => 'user', 'id' => '2']]],
                ],
            ],
            'included' => [['type' => 'user', 'id' => '2', 'attributes' => ['name' => 'Alan']]],
            'jsonapi' => ['version' => '1.1', 'ext' => ['https://example.com/ext'], 'profile' => []],
        ]));
    }

    public function test_an_integer_id_is_a_violation(): void
    {
        self::assertNotSame([], JsonApiSchema::errors(['data' => ['type' => 'user', 'id' => 1]]));
    }

    public function test_an_empty_relationship_list_is_a_violation(): void
    {
        self::assertNotSame([], JsonApiSchema::errors([
            'data' => ['type' => 'user', 'id' => '1', 'relationships' => ['boss' => []]],
        ]));
    }

    public function test_a_document_given_as_json_is_decoded_as_objects(): void
    {
        self::assertSame([], JsonApiSchema::errors('{"data":{"type":"user","id":"1","attributes":{}}}'));
        self::assertNotSame([], JsonApiSchema::errors('{"data":{"type":"user","id":"1","attributes":[]}}'));
    }

    public function test_a_violation_names_where_it_is(): void
    {
        $errors = JsonApiSchema::errors(['data' => ['type' => 'user', 'id' => 1]]);

        self::assertStringContainsString('/data', implode("\n", $errors));
    }

    public function test_a_body_that_is_not_json_is_a_violation(): void
    {
        self::assertSame(['/: The document is not valid JSON (Syntax error).'], JsonApiSchema::errors('<html>'));
    }
}
