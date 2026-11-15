<?php

declare(strict_types=1);

namespace Test\Unit\Descriptors\Schemas;

use Ark4ne\ApiRevisions\Compilation\ShapeSelector;
use Ark4ne\ApiRevisions\Projection\Projector;
use Ark4ne\ApiRevisions\Revisions\Unrevisioned;
use Ark4ne\ApiRevisions\Schema\DeclarationKind;
use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\ApiRevisions\Schema\TypeRegistry;
use Ark4ne\JsonApi\Descriptors\Schemas\DecimalSchema;
use Ark4ne\JsonApi\Descriptors\Schemas\JsonSchema;
use Ark4ne\JsonApi\Descriptors\Schemas\MixedSchema;
use Ark4ne\JsonApi\Descriptors\Schemas\SchemaTypes;
use PHPUnit\Framework\TestCase;

final class SchemaTypesTest extends TestCase
{
    protected function setUp(): void
    {
        TypeRegistry::flush();
    }

    protected function tearDown(): void
    {
        TypeRegistry::flush();
        SchemaTypes::register();
    }

    public function test_the_types_of_the_package_are_made_by_the_registry_of_the_base(): void
    {
        SchemaTypes::register();

        self::assertInstanceOf(DecimalSchema::class, TypeRegistry::make('decimal', ['price']));
        self::assertInstanceOf(JsonSchema::class, TypeRegistry::make('json', []));
        self::assertInstanceOf(MixedSchema::class, TypeRegistry::make('mixed', [static fn(): int => 1]));
    }

    public function test_a_type_the_application_registered_first_keeps_its_name(): void
    {
        TypeRegistry::register('mixed', JsonSchema::class);

        SchemaTypes::register();

        self::assertInstanceOf(JsonSchema::class, TypeRegistry::make('mixed', []));
        self::assertInstanceOf(DecimalSchema::class, TypeRegistry::make('decimal', []));
    }

    public function test_the_types_are_projected_by_the_base(): void
    {
        SchemaTypes::register();
        $descriptors = [
            'price' => Schema::decimal()->scale(2),
            'settings' => Schema::json('raw'),
            'anything' => Schema::mixed()->nullable(),
        ];
        $shape = ShapeSelector::at($descriptors, Unrevisioned::Head, DeclarationKind::Response->direction());

        $projected = (new Projector())->project($shape, $descriptors, ['price' => 12.5, 'raw' => '{"dark":true}', 'anything' => null]);

        self::assertSame(['price' => '12.50', 'settings' => ['dark' => true], 'anything' => null], $projected);
    }
}
