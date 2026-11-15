<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Compilation;

use Ark4ne\ApiRevisions\Compilation\FieldDefinition;
use Ark4ne\ApiRevisions\Compilation\Shape;
use Ark4ne\ApiRevisions\Definition\Kind;
use Ark4ne\ApiRevisions\Definition\TypeDefinition;
use Ark4ne\JsonApi\Definition\AttributeDefinition;
use Ark4ne\JsonApi\Definition\ValueType;
use UnitEnum;

/**
 * The attributes of a definition from a shape of `ark4ne/laravel-api-revisions`: what the tools reading a
 * `ResourceDefinition` see of a resource declared with the descriptors of the base, at the revision of the shape.
 *
 * @internal Until the definition gives the model of the base itself (docs/rfc/api-revisions-integration.md D10).
 */
final class SchemaAttributes
{
    private function __construct() {}

    /**
     * The fields visible at the revision of the shape, under their names at it, in the head order.
     *
     * @return list<AttributeDefinition>
     */
    public static function of(Shape $shape): array
    {
        return array_values(array_map(self::field(...), $shape->fields));
    }

    /**
     * The name each field of the code has at the revision of the shape, for those visible at it.
     *
     * @return array<string, string>
     */
    public static function names(Shape $shape): array
    {
        return array_map(static fn(FieldDefinition $field): string => $field->name, $shape->fields);
    }

    private static function field(FieldDefinition $field): AttributeDefinition
    {
        $definition = self::type($field->name, $field->type)
            ->nullable($field->nullable)
            ->conditional($field->conditional);

        // The children of a head object carry their own presence; those of a `was()` schema only their type.
        return $field->children === null ? $definition : $definition->children(...self::of($field->children));
    }

    private static function type(string $name, TypeDefinition $type): AttributeDefinition
    {
        $definition = AttributeDefinition::of($name, self::valueType($type))->nullable($type->nullable);

        return match ($type->kind) {
            Kind::String => $definition->format($type->format),
            Kind::Date, Kind::DateTime => $definition->format($type->format),
            Kind::Enum => $definition->enum(self::enum($type->enum)),
            Kind::List => $definition->items($type->items === null ? null : self::type('*', $type->items)),
            Kind::Object => $definition->children(...array_map(self::type(...), array_keys($type->children), array_values($type->children))),
            Kind::Custom => $definition->format(self::fragment($type, 'format') ?? ($definition->type === ValueType::Mixed ? $type->name : null)),
            default => $definition,
        };
    }

    private static function valueType(TypeDefinition $type): ValueType
    {
        return match ($type->kind) {
            Kind::String => ValueType::String,
            Kind::Int => ValueType::Integer,
            Kind::Float => ValueType::Number,
            Kind::Bool => ValueType::Boolean,
            Kind::Date, Kind::DateTime => ValueType::Date,
            Kind::Enum => ValueType::Enum,
            Kind::List => ValueType::Array,
            Kind::Object => ValueType::Struct,
            Kind::Any => ValueType::Mixed,
            // A custom type is what its JSON Schema fragment says it renders.
            Kind::Custom => match (self::fragment($type, 'type')) {
                'string' => ValueType::String,
                'integer' => ValueType::Integer,
                'number' => ValueType::Number,
                'boolean' => ValueType::Boolean,
                'array' => ValueType::Array,
                default => ValueType::Mixed,
            },
        };
    }

    private static function fragment(TypeDefinition $type, string $keyword): ?string
    {
        $value = $type->fragment[$keyword] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @return class-string<UnitEnum>|null
     */
    private static function enum(?string $enum): ?string
    {
        return $enum !== null && enum_exists($enum) ? $enum : null;
    }
}
