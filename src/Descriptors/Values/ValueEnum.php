<?php

namespace Ark4ne\JsonApi\Descriptors\Values;

use BackedEnum;
use UnitEnum;

/**
 * @template T
 * @extends Value<T>
 */
class ValueEnum extends Value
{
    protected function value(mixed $of): mixed
    {
        if ($of instanceof BackedEnum) return $of->value;
        if ($of instanceof UnitEnum) return $of->name;

        return $of;
    }
}
