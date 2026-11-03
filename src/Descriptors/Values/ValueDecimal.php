<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Values;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\ValueType;
use Illuminate\Http\Request;

/**
 * An exact decimal number rendered as a string (`"1234.50"`), as a `decimal` column holds it: no float on the way, so
 * no digit is lost. `scale()` sets the number of decimals, rounding half away from zero.
 *
 * @template T
 * @extends Value<T>
 */
final class ValueDecimal extends Value
{
    protected ?int $scale = null;

    /**
     * Renders `$scale` decimals, rounding half away from zero (`1.235` gives `1.24` with 2) or padding with zeros.
     */
    public function scale(int $scale): static
    {
        return $this->with('scale', max(0, $scale));
    }

    public function value(mixed $of, Request $request, Configuration $configuration): string
    {
        [$sign, $integer, $fraction] = self::parse(match (true) {
            is_int($of), is_string($of) && is_numeric($of) => trim((string) $of),
            is_float($of) => self::fromFloat($of),
            default => '0',
        });

        if ($this->scale !== null) {
            [$integer, $fraction] = self::round($integer, $fraction, $this->scale);
        }

        $decimal = $fraction === '' ? $integer : "{$integer}.{$fraction}";

        return $sign === '-' && trim($decimal, '0.') !== '' ? "-{$decimal}" : $decimal;
    }

    protected function valueType(): ValueType
    {
        return ValueType::String;
    }

    protected function valueFormat(Configuration $configuration): string
    {
        return 'decimal';
    }

    /**
     * The shortest decimal writing of a float that reads back the same (`0.1`, not `0.1000000000000000055`), digit
     * by digit (`1.0E+25` written out); `0` for a float that is not finite.
     */
    private static function fromFloat(float $float): string
    {
        if (!is_finite($float)) {
            return '0';
        }

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
