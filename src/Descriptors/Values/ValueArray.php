<?php

namespace Ark4ne\JsonApi\Descriptors\Values;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * @template T
 * @extends Value<T>
 */
class ValueArray extends Value
{
    /**
     * @param mixed $of
     * @param Request $request
     * @return array<array-key, mixed>
     */
    public function value(mixed $of, Request $request): array
    {
        return (new Collection($of))->toArray();
    }
}
