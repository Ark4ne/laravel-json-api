<?php

namespace Ark4ne\JsonApi\Descriptors\Values;

use Illuminate\Http\Request;

/**
 * @template T
 * @extends Value<T>
 */
class ValueString extends Value
{
    public function value(mixed $of, Request $request): string
    {
        return (string)$of;
    }
}
