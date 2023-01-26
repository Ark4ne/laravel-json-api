<?php

namespace Ark4ne\JsonApi\Descriptors\Values;

/**
 * @template T
 * @extends Value<T>
 */
class ValueInteger extends Value
{
    public function value(mixed $of): int
    {
        return (int)$of;
    }
}
