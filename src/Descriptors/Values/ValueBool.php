<?php

namespace Ark4ne\JsonApi\Descriptors\Values;

/**
 * @template T as \Illuminate\Database\Eloquent\Model
 * @extends Value<T>
 */
class ValueBool extends Value
{
    public function value(mixed $of): bool
    {
        return (bool)$of;
    }
}
