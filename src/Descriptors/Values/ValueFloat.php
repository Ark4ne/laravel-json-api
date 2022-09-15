<?php

namespace Ark4ne\JsonApi\Descriptors\Values;

class ValueFloat extends Value
{
    protected int $precision;

    public function precision(int $precision): static
    {
        $this->precision = $precision;
        return $this;
    }

    public function value(mixed $of): float
    {
        if (isset($this->precision)) {
            $precision = 10 ** $this->precision;

            return floor((float)$of * $precision) / $precision;
        }

        return (float)$of;
    }
}
