<?php

declare(strict_types=1);

namespace Test\Unit\Definition;

use Ark4ne\JsonApi\Definition\AttributeDefinition;
use Ark4ne\JsonApi\Definition\ValueType;
use PHPUnit\Framework\TestCase;
use Test\app\Enums\State;

final class AttributeDefinitionTest extends TestCase
{
    public function test_an_attribute_is_nullable_unconditional_and_read_from_nothing_by_default(): void
    {
        $attribute = AttributeDefinition::of('title', ValueType::String);

        self::assertSame('title', $attribute->name);
        self::assertSame(ValueType::String, $attribute->type);
        self::assertTrue($attribute->nullable);
        self::assertFalse($attribute->conditional);
        self::assertFalse($attribute->sparse);
        self::assertFalse($attribute->computed);
        self::assertNull($attribute->source);
    }

    public function test_withers_return_a_new_attribute(): void
    {
        $attribute = AttributeDefinition::of('state', ValueType::Mixed);

        $changed = $attribute
            ->name('status')
            ->type(ValueType::Enum)
            ->enum(State::class)
            ->nullable(false)
            ->conditional()
            ->sparse()
            ->source('state')
            ->computed();

        self::assertSame('state', $attribute->name);
        self::assertSame(ValueType::Mixed, $attribute->type);
        self::assertSame('status', $changed->name);
        self::assertSame(State::class, $changed->enum);
        self::assertFalse($changed->nullable);
        self::assertTrue($changed->conditional);
        self::assertTrue($changed->sparse);
        self::assertSame('state', $changed->source);
        self::assertTrue($changed->computed);
    }

    public function test_a_nullable_field_can_be_reset_to_null(): void
    {
        $attribute = AttributeDefinition::of('date', ValueType::Date)->format('Y-m-d')->source('date');

        self::assertNull($attribute->format(null)->format);
        self::assertNull($attribute->source(null)->source);
        self::assertNull($attribute->items(AttributeDefinition::of('item', ValueType::String))->items(null)->items);
    }

    public function test_children_are_indexed_by_name_and_give_dotted_paths(): void
    {
        $struct = AttributeDefinition::of('address', ValueType::Struct)->children(
            AttributeDefinition::of('city', ValueType::String),
            AttributeDefinition::of('geo', ValueType::Struct)->children(AttributeDefinition::of('lat', ValueType::Number)),
        );

        self::assertSame(['city', 'geo'], array_keys($struct->children));
        self::assertSame(['address', 'address.city', 'address.geo', 'address.geo.lat'], $struct->paths());
        self::assertSame(['user.address', 'user.address.city', 'user.address.geo', 'user.address.geo.lat'], $struct->paths('user'));
    }
}
