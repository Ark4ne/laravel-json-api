<?php

namespace Ark4ne\JsonApi\Descriptors\Values;

use Illuminate\Http\Request;

/**
 * @template T
 * @extends Value<T>
 */
class ValueInteger extends Value
{
    public function value(mixed $of, Request $request): int
    {
        return (int)$of;
    }
}
