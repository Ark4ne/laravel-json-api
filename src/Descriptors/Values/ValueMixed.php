<?php

namespace Ark4ne\JsonApi\Descriptors\Values;

class ValueMixed extends Value
{
    public function value(mixed $of): mixed
    {
        return $of;
    }
}
