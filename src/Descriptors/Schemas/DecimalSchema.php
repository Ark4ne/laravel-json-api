<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Schemas;

use Ark4ne\ApiRevisions\Definition\Kind;
use Ark4ne\ApiRevisions\Definition\TypeDefinition;
use Ark4ne\ApiRevisions\Schema\Descriptor;
use Ark4ne\ApiRevisions\Schema\InvalidValue;

/**
 * An exact decimal number rendered as a string (`"1234.50"`), as a `decimal` column holds it: no float on the way, so
 * no digit is lost. `scale()` sets the number of decimals, rounding half away from zero.
 *
 * A custom type of `ark4ne/laravel-api-revisions` (`decimal`): accepts an int, a numeric string or a finite float;
 * anything else is a value error (cast to `"0"`).
 *
 * @extends Descriptor<numeric-string>
 */
final class DecimalSchema extends Descriptor
{
    /** @var int<0, max>|null */
    private ?int $scale = null;

    /**
     * Renders `$scale` decimals, rounding half away from zero (`1.235` gives `1.24` with 2) or padding with zeros.
     */
    public function scale(int $scale): self
    {
        $clone = clone $this;
        $clone->scale = max(0, $scale);

        return $clone;
    }

    public function normalize(mixed $value): mixed
    {
        $number = match (true) {
            is_int($value) => (string) $value,
            is_string($value) && is_numeric($value) => trim($value),
            is_float($value) && is_finite($value) => self::fromFloat($value),
            default => null,
        };

        return $number === null ? new InvalidValue($value, Kind::Custom, $this->write('0')) : $this->write($number);
    }

    protected function type(): TypeDefinition
    {
        return TypeDefinition::custom('decimal', [
            'type' => 'string',
            'format' => 'decimal',
            'pattern' => $this->scale === null || $this->scale === 0
                ? ($this->scale === null ? '^-?\d+(\.\d+)?$' : '^-?\d+$')
                : '^-?\d+\.\d{' . $this->scale . '}$',
        ]);
    }

    /**
     * The decimal writing of a number, at the scale.
     *
     * @return numeric-string
     */
    private function write(string $number): string
    {
        [$sign, $integer, $fraction] = self::parse($number);

        if ($this->scale !== null) {
            [$integer, $fraction] = self::round($integer, $fraction, $this->scale);
        }

        $decimal = $fraction === '' ? $integer : "{$integer}.{$fraction}";

        /** @var numeric-string */
        return $sign === '-' && trim($decimal, '0.') !== '' ? "-{$decimal}" : $decimal;
    }

    /**
     * The shortest decimal writing of a finite float that reads back the same (`0.1`, not `0.1000000000000000055`),
     * digit by digit (`1.0E+25` written out).
     */
    private static function fromFloat(float $float): string
    {
        // `2.0` is written `2`.
        $written = var_export($float, true);

        return stripos($written, 'e') === false
            ? preg_replace('/\.0$/', '', $written) ?? $written
            : rtrim(rtrim(sprintf('%.20F', $float), '0'), '.');
    }

    /**
     * @return array{string, string, string} The sign, the integer digits (`0` at least) and the fraction digits
     */
    private static function parse(string $number): array
    {
        // An exponent (`1e3`) is not written digit by digit: through a float.
        if (preg_match('/^([+-]?)(\d*)(?:\.(\d*))?$/', $number, $parts) !== 1) {
            return self::parse(self::fromFloat((float) $number));
        }

        $integer = ltrim($parts[2], '0');

        return [$parts[1], $integer === '' ? '0' : $integer, $parts[3] ?? ''];
    }

    /**
     * @return array{string, string} The integer and fraction digits rounded to `$scale` decimals
     */
    private static function round(string $integer, string $fraction, int $scale): array
    {
        $fraction = str_pad($fraction, $scale + 1, '0');
        $digits = $integer . substr($fraction, 0, $scale);

        if ((int) $fraction[$scale] >= 5) {
            $digits = self::increment($digits);
        }

        $integer = substr($digits, 0, strlen($digits) - $scale);
        $integer = $integer === '' ? '0' : $integer;

        return [$integer, $scale === 0 ? '' : substr($digits, -$scale)];
    }

    /**
     * Adds one to a string of digits (`'0999'` gives `'1000'`).
     */
    private static function increment(string $digits): string
    {
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            if ($digits[$i] !== '9') {
                return substr($digits, 0, $i) . ((int) $digits[$i] + 1) . str_repeat('0', strlen($digits) - $i - 1);
            }
        }

        return '1' . str_repeat('0', strlen($digits));
    }
}
