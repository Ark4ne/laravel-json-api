<?php

namespace Ark4ne\JsonApi\Descriptors\Values;

class ValueBool extends Value
{
    public function value(mixed $of): bool
    {
        return (bool)$of;
    }
}
