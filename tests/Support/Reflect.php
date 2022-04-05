<?php

namespace Test\Support;

class Reflect
{
    public static function invoke($object, string $method, ...$args)
    {
        $reflect = new \ReflectionClass($object);
        $reflectMethod = $reflect->getMethod($method);
        $reflectMethod->setAccessible(true);

        return $reflectMethod->invoke($object, ...$args);
    }

}
