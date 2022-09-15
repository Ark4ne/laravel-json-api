<?php

namespace Ark4ne\JsonApi\Descriptors\Values;

class ValueInteger extends Value
{
    public function value(mixed $of): int
    {
        return (int)$of;
    }
}
