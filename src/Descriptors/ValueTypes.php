<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Descriptors\Values\Value;
use Ark4ne\JsonApi\Descriptors\Values\ValueArray;
use Ark4ne\JsonApi\Descriptors\Values\ValueBool;
use Ark4ne\JsonApi\Descriptors\Values\ValueDate;
use Ark4ne\JsonApi\Descriptors\Values\ValueEnum;
use Ark4ne\JsonApi\Descriptors\Values\ValueFloat;
use Ark4ne\JsonApi\Descriptors\Values\ValueInteger;
use Ark4ne\JsonApi\Descriptors\Values\ValueMixed;
use Ark4ne\JsonApi\Descriptors\Values\ValueString;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Exceptions\InvalidConfigurationException;
use Closure;
use ReflectionClass;

/**
 * The value types by name (**Strategy** + **Registry**, docs/architecture.md §5): those of the package, then those
 * the application registers (`describer.types`, `JsonApi::valueType()`). Each `Value` declares its `ValueType` and its
 * cast; a registered type is written `$this->money()`, or named as the item type of an array (`->of('money')`).
 */
final class ValueTypes
{
    /**
     * The types of the package that are built from an attribute alone: a struct needs its closure.
     */
    public const BUILT_IN = [
        'bool' => ValueBool::class,
        'integer' => ValueInteger::class,
        'float' => ValueFloat::class,
        'string' => ValueString::class,
        'date' => ValueDate::class,
        'array' => ValueArray::class,
        'mixed' => ValueMixed::class,
        'enum' => ValueEnum::class,
    ];

    private function __construct() {}

    /**
     * The class of a value type, by its name or its class.
     *
     * @return class-string<Value<mixed>>|null `null` when the name is neither registered nor a value class
     */
    public static function classOf(Configuration $configuration, string $type): ?string
    {
        $class = self::BUILT_IN[$type] ?? $configuration->valueTypes[$type] ?? $type;

        return is_subclass_of($class, Value::class) ? $class : null;
    }

    /**
     * A new descriptor of a value type, by its name or its class, for an attribute.
     *
     * @return Value<mixed>
     *
     * @throws DefinitionException when the type is unknown or needs more than an attribute (a struct)
     */
    public static function make(Configuration $configuration, string $type, string|Closure|null $attribute = null): Value
    {
        $class = self::classOf($configuration, $type) ?? throw DefinitionException::unknownValueType($type);

        if ($attribute === null && !self::buildableWithoutAttribute($class)) {
            throw DefinitionException::valueTypeNeedsInstance($class);
        }

        return new $class($attribute);
    }

    /**
     * Checks the types the application registers: each one a `Value`, under a name no method of the resources has.
     *
     * @param list<string> $reserved The names taken by the methods of the resources
     *
     * @throws InvalidConfigurationException naming `describer.types`
     */
    public static function validate(Configuration $configuration, array $reserved = []): void
    {
        foreach ($configuration->valueTypes as $name => $class) {
            if (!is_subclass_of($class, Value::class)) {
                throw InvalidConfigurationException::forKey("describer.types.{$name}", 'a class extending ' . Value::class);
            }

            if (isset(self::BUILT_IN[$name]) || in_array($name, $reserved, true)) {
                throw InvalidConfigurationException::forKey("describer.types.{$name}", 'a name no resource method has');
            }
        }
    }

    /**
     * @param class-string<Value<mixed>> $class
     */
    private static function buildableWithoutAttribute(string $class): bool
    {
        $parameter = (new ReflectionClass($class))->getConstructor()?->getParameters()[0] ?? null;

        return $parameter === null || $parameter->allowsNull() || $parameter->isOptional();
    }
}
