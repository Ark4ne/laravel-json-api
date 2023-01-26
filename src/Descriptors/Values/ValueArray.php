<?php

namespace Ark4ne\JsonApi\Descriptors\Values;

use Illuminate\Support\Collection;

/**
 * @template T
 * @extends Value<T>
 */
class ValueArray extends Value
{
    /**
     * @param mixed $of
     *
     * @return array<array-key, mixed>
     */
    public function value(mixed $of): array
    {
        return (new Collection($of))->toArray();
    }
}
