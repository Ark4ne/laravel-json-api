<?php

namespace Test\Support;

use ReflectionClass;

class Reflect
{
    public static function invoke($object, string $method, ...$args)
    {
        $reflect = new ReflectionClass($object);
        $reflectMethod = $reflect->getMethod($method);
        $reflectMethod->setAccessible(true);

        return $reflectMethod->invoke($object, ...$args);
    }

    public static function set($object, string $property, $value)
    {
        $reflect = new ReflectionClass($object);
        $reflectProperty = $reflect->getProperty($property);
        $reflectProperty->setAccessible(true);

        $reflectProperty->setValue($object, $value);
    }
}
