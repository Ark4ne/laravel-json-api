<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Laravel;

use Ark4ne\JsonApi\Compilation\CompiledDefinitions;
use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Descriptors\ValueTypes;
use Ark4ne\JsonApi\Exceptions\InvalidConfigurationException;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use ReflectionClass;
use ReflectionMethod;

/**
 * The entry point of an application into the package, called from its service providers.
 */
final class JsonApi
{
    private function __construct() {}

    /**
     * Registers a value type of the application: `$this->money()` in a resource, `->of('money')` as the items of an
     * array; the same as `describer.types`. Call it in the `register()` or `boot()` of a service provider.
     *
     * @param class-string<\Ark4ne\JsonApi\Descriptors\Values\Value<mixed>> $class
     *
     * @throws InvalidConfigurationException when the name is taken or the class is not a value
     */
    public static function valueType(string $name, string $class): void
    {
        /** @var Configuration $configuration */
        $configuration = app(Configuration::class);
        $configuration = $configuration->withValueType($name, $class);

        ValueTypes::validate($configuration, self::reservedValueTypeNames());

        app()->instance(Configuration::class, $configuration);

        /** @var CompiledDefinitions $compiled */
        $compiled = app(CompiledDefinitions::class);
        Definitions::configure($configuration, $compiled);
    }

    /**
     * The names of the methods of the resources, which a value type cannot take.
     *
     * @return list<string>
     *
     * @internal
     */
    public static function reservedValueTypeNames(): array
    {
        return array_map(
            static fn(ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass(JsonApiResource::class))->getMethods(),
        );
    }
}
