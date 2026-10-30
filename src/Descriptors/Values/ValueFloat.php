<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Values;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\ValueType;
use Illuminate\Http\Request;

/**
 * @template T
 * @extends Value<T>
 */
class ValueFloat extends Value
{
    /** `null` until set: the configuration decides (`describer.precision`). */
    protected ?int $precision = null;

    protected bool $truncate = false;

    /**
     * Rounds the value to `$precision` decimals (`1.239` gives `1.24` with 2).
     */
    public function precision(int $precision): static
    {
        return $this->with('precision', $precision);
    }

    /**
     * Truncates to the precision instead of rounding, towards zero (`1.239` gives `1.23`, `-1.239` gives `-1.23`).
     */
    public function truncate(bool $truncate = true): static
    {
        return $this->with('truncate', $truncate);
    }

    public function value(mixed $of, Request $request, Configuration $configuration): float
    {
        $float = (float) $of;
        $precision = $this->precision ?? $configuration->precision;

        if ($precision === null) {
            return $float;
        }

        if (!$this->truncate) {
            return round($float, $precision);
        }

        // A step lost in the binary representation is restored first: 1.15 * 100 is 114.99999999999999.
        $factor = 10 ** $precision;
        $scaled = $float * $factor;
        $step = round($scaled);
        $scaled = abs($scaled - $step) <= 4 * PHP_FLOAT_EPSILON * max(1.0, abs($scaled)) ? $step : $scaled;

        return ($scaled < 0 ? ceil($scaled) : floor($scaled)) / $factor;
    }

    protected function valueType(): ValueType
    {
        return ValueType::Number;
    }
}
