<?php

namespace Ark4ne\JsonApi\Descriptors\Values;

use Ark4ne\JsonApi\Support\Config;
use Closure;
use Illuminate\Http\Request;

/**
 * @template T
 * @extends Value<T>
 */
class ValueFloat extends Value
{
    protected int|null $precision;

    public function __construct(string|Closure|null $attribute)
    {
        parent::__construct($attribute);

        $this->precision = Config::$precision;
    }

    public function precision(int $precision): static
    {
        $this->precision = $precision;
        return $this;
    }

    public function value(mixed $of, Request $request): float
    {
        if (isset($this->precision)) {
            $precision = 10 ** $this->precision;

            return floor((float)$of * $precision) / $precision;
        }

        return (float)$of;
    }
}
