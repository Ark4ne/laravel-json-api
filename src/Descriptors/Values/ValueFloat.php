<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Values;

use Ark4ne\JsonApi\Definition\ValueType;
use Ark4ne\JsonApi\Support\Config;
use Illuminate\Http\Request;

/**
 * @template T
 * @extends Value<T>
 */
class ValueFloat extends Value
{
    /** `null` until set: the configuration decides (`describer.precision`). */
    protected ?int $precision = null;

    public function precision(int $precision): static
    {
        return $this->with('precision', $precision);
    }

    public function value(mixed $of, Request $request): float
    {
        $precision = $this->precision ?? Config::$precision;

        if ($precision !== null) {
            $precision = 10 ** $precision;

            return floor((float) $of * $precision) / $precision;
        }

        return (float) $of;
    }

    protected function valueType(): ValueType
    {
        return ValueType::Number;
    }
}
