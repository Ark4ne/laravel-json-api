<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Schemas;

use Ark4ne\ApiRevisions\Definition\Kind;
use Ark4ne\ApiRevisions\Definition\TypeDefinition;
use Ark4ne\ApiRevisions\Schema\Descriptor;
use Ark4ne\ApiRevisions\Schema\InvalidValue;

/**
 * A float that may be rounded: `precision()` sets the number of decimals it is rounded to, `truncate()` truncates to
 * them instead. Without a precision of its own it takes the configured one (`describer.precision`), none by default.
 *
 * For `ark4ne/laravel-api-revisions` it is its float (`Kind::Float`: same values accepted, same conversions, same
 * export): only the value differs, rounded before any conversion to an older revision.
 *
 * @extends Descriptor<float>
 */
final class FloatSchema extends Descriptor
{
    private int|float|null $min = null;

    private int|float|null $max = null;

    private ?int $precision = null;

    private bool $truncate = false;

    public function min(int|float $min): self
    {
        $clone = clone $this;
        $clone->min = $min;

        return $clone;
    }

    public function max(int|float $max): self
    {
        $clone = clone $this;
        $clone->max = $max;

        return $clone;
    }

    /**
     * Rounds the value to `$precision` decimals, half away from zero (`1.239` gives `1.24` with 2); `null` for the
     * value as it is.
     */
    public function precision(?int $precision): self
    {
        $clone = clone $this;
        $clone->precision = $precision;

        return $clone;
    }

    /**
     * Truncates to the precision instead of rounding, towards zero (`1.239` gives `1.23`, `-1.239` gives `-1.23`).
     */
    public function truncate(bool $truncate = true): self
    {
        $clone = clone $this;
        $clone->truncate = $truncate;

        return $clone;
    }

    /**
     * The number of decimals the value is rounded to, `null` for none.
     */
    public function rounding(): ?int
    {
        return $this->precision;
    }

    public function normalize(mixed $value): mixed
    {
        $float = match (true) {
            is_int($value), is_float($value), is_string($value) && is_numeric($value) && trim($value) === $value => (float) $value,
            default => null,
        };
        if ($float !== null && is_finite($float)) {
            return $this->round($float);
        }

        return new InvalidValue($value, Kind::Float, is_scalar($value) && $float === null ? $this->round((float) $value) : null);
    }

    protected function type(): TypeDefinition
    {
        return TypeDefinition::of(Kind::Float)->withMin($this->min)->withMax($this->max);
    }

    private function round(float $float): float
    {
        if ($this->precision === null || !is_finite($float)) {
            return $float;
        }

        if (!$this->truncate) {
            return round($float, $this->precision);
        }

        // A step lost in the binary writing is restored first: 1.15 * 100 is 114.99999999999999.
        $factor = 10 ** $this->precision;
        $scaled = $float * $factor;
        $step = round($scaled);
        $scaled = abs($scaled - $step) <= 4 * PHP_FLOAT_EPSILON * max(1.0, abs($scaled)) ? $step : $scaled;

        return ($scaled < 0 ? ceil($scaled) : floor($scaled)) / $factor;
    }
}
