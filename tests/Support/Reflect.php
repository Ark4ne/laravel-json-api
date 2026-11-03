<?php

declare(strict_types=1);

namespace Test\Support;

use ReflectionClass;

/**
 * Reads and calls the members a test cannot reach otherwise.
 */
final class Reflect
{
    public static function invoke(object $object, string $method, mixed ...$args): mixed
    {
        $reflect = new ReflectionClass($object);
        $reflectMethod = $reflect->getMethod($method);

        return $reflectMethod->invoke($object, ...$args);
    }

    public static function set(object $object, string $property, mixed $value): void
    {
        $reflect = new ReflectionClass($object);
        $reflectProperty = $reflect->getProperty($property);

        $reflectProperty->setValue($object, $value);
    }

    public static function get(object $object, string $property): mixed
    {
        $reflect = new ReflectionClass($object);
        $reflectProperty = $reflect->getProperty($property);

        return $reflectProperty->getValue($object);
    }
}
