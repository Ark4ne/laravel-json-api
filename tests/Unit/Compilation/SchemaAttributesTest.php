<?php

declare(strict_types=1);

namespace Test\Unit\Compilation;

use Ark4ne\ApiRevisions\Compilation\Shape;
use Ark4ne\ApiRevisions\Compilation\ShapeSelector;
use Ark4ne\ApiRevisions\Schema\DeclarationKind;
use Ark4ne\ApiRevisions\Schema\Descriptor;
use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\JsonApi\Compilation\SchemaAttributes;
use Ark4ne\JsonApi\Definition\AttributeDefinition;
use Ark4ne\JsonApi\Definition\ValueType;
use Ark4ne\JsonApi\Descriptors\Schemas\DecimalSchema;
use Ark4ne\JsonApi\Descriptors\Schemas\JsonSchema;
use Ark4ne\JsonApi\Descriptors\Schemas\MixedSchema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Test\app\Enums\State;
use Test\app\Revisions\ApiRevision;

final class SchemaAttributesTest extends TestCase
{
    /**
     * @return iterable<string, array{Descriptor<mixed>, ValueType, string|null}>
     */
    public static function types(): iterable
    {
        yield 'string' => [Schema::string(), ValueType::String, null];
        yield 'string with a format' => [Schema::string()->format('uuid'), ValueType::String, 'uuid'];
        yield 'int' => [Schema::int(), ValueType::Integer, null];
        yield 'float' => [Schema::float(), ValueType::Number, null];
        yield 'bool' => [Schema::bool(), ValueType::Boolean, null];
        yield 'date' => [Schema::date(), ValueType::Date, 'Y-m-d'];
        yield 'date and time' => [Schema::dateTime()->format('H:i:s'), ValueType::Date, 'H:i:s'];
        yield 'decimal' => [new DecimalSchema(), ValueType::String, 'decimal'];
        yield 'json' => [new JsonSchema(), ValueType::Mixed, 'json'];
        yield 'mixed' => [new MixedSchema(), ValueType::Mixed, null];
    }

    /**
     * @param Descriptor<mixed> $descriptor
     */
    #[DataProvider('types')]
    public function test_a_field_is_described_by_its_type_at_the_revision(Descriptor $descriptor, ValueType $type, ?string $format): void
    {
        $attribute = self::attributes(['field' => $descriptor])['field'];

        self::assertSame('field', $attribute->name);
        self::assertSame($type, $attribute->type);
        self::assertSame($format, $attribute->format);
        self::assertFalse($attribute->nullable);
        self::assertFalse($attribute->conditional);
    }

    public function test_an_enum_names_its_class(): void
    {
        $attribute = self::attributes(['role' => Schema::enum(State::class)])['role'];

        self::assertSame(ValueType::Enum, $attribute->type);
        self::assertSame(State::class, $attribute->enum);
    }

    public function test_a_list_describes_its_items_and_an_object_its_children(): void
    {
        $attributes = self::attributes([
            'tags' => Schema::list(Schema::string()),
            'address' => Schema::object(['city' => Schema::string()->nullable(), 'zip' => Schema::int()]),
        ]);

        self::assertSame(ValueType::Array, $attributes['tags']->type);
        self::assertSame(ValueType::String, $attributes['tags']->items?->type);
        self::assertSame(ValueType::Struct, $attributes['address']->type);
        self::assertSame(['city', 'zip'], array_keys($attributes['address']->children));
        self::assertTrue($attributes['address']->children['city']->nullable);
        self::assertSame(ValueType::Integer, $attributes['address']->children['zip']->type);
    }

    public function test_the_presence_of_a_field_is_described(): void
    {
        $attributes = self::attributes([
            'nickname' => Schema::string()->nullable(),
            'email' => Schema::string()->whenHas(),
        ]);

        self::assertTrue($attributes['nickname']->nullable);
        self::assertFalse($attributes['nickname']->conditional);
        self::assertTrue($attributes['email']->conditional);
    }

    public function test_the_fields_have_their_names_and_types_at_the_revision(): void
    {
        $descriptors = [
            'title' => Schema::string()->was(ApiRevision::Initial, name: 'name'),
            'views' => Schema::int()->was(ApiRevision::Initial, schema: Schema::string()),
            'summary' => Schema::string()->since(ApiRevision::Labels),
        ];

        $initial = self::attributes($descriptors, ApiRevision::Initial);

        self::assertSame(['name', 'views'], array_keys($initial));
        self::assertSame(ValueType::String, $initial['views']->type);
        self::assertSame(['title', 'views', 'summary'], array_keys(self::attributes($descriptors)));
        self::assertSame(['title' => 'name', 'views' => 'views'], SchemaAttributes::names(self::shape($descriptors, ApiRevision::Initial)));
    }

    /**
     * @param array<string, Descriptor<mixed>> $descriptors
     *
     * @return array<string, AttributeDefinition>
     */
    private static function attributes(array $descriptors, ApiRevision $revision = ApiRevision::UsersReplaceTeachers): array
    {
        $attributes = [];
        foreach (SchemaAttributes::of(self::shape($descriptors, $revision)) as $attribute) {
            $attributes[$attribute->name] = $attribute;
        }

        return $attributes;
    }

    /**
     * @param array<string, Descriptor<mixed>> $descriptors
     */
    private static function shape(array $descriptors, ApiRevision $revision): Shape
    {
        return ShapeSelector::at($descriptors, $revision, DeclarationKind::Response->direction());
    }
}
