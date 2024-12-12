<?php

namespace Ark4ne\JsonApi\Descriptors\Values;

use Closure;

/**
 * @internal
 * @template T
 * @extends Value<T>
 */
class ValueRaw extends Value
{
    public function __construct(
        null|string|Closure $attribute,
        protected mixed $raw
    ) {
        parent::__construct($attribute);
    }

    public function value(mixed $of): mixed
    {
        return $this->raw;
    }
}
