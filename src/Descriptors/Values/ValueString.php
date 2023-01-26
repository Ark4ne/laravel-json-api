<?php

namespace Ark4ne\JsonApi\Descriptors\Values;

/**
 * @template T
 * @extends Value<T>
 */
class ValueString extends Value
{
    public function value(mixed $of): string
    {
        return (string)$of;
    }
}
