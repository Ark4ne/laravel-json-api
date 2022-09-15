<?php

namespace Ark4ne\JsonApi\Descriptors\Values;

class ValueString extends Value
{
    public function value(mixed $of): string
    {
        return (string)$of;
    }
}
