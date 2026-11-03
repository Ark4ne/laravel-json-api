<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Values;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\ValueType;
use Ark4ne\JsonApi\Exceptions\RenderingException;
use DateTime;
use DateTimeInterface;
use Exception;
use Illuminate\Http\Request;
use Stringable;

/**
 * A date and time, in the configured format (`describer.datetime`, ATOM by default) unless `format()` says otherwise;
 * the base of `ValueDate` and `ValueTime`.
 *
 * @template T
 * @extends Value<T>
 */
class ValueDateTime extends Value
{
    /** `null` until set: the configuration decides (`describer.datetime`). */
    protected ?string $format = null;

    public function format(string $format): static
    {
        return $this->with('format', $format);
    }


    /**
     * `null` stays `null`, even when not nullable: no date stands for it.
     *
     * @throws Exception
     */
    public function value(mixed $of, Request $request, Configuration $configuration): ?string
    {
        if ($of === null) {
            return null;
        }
        $format = $this->valueFormat($configuration);

        if ($of instanceof DateTimeInterface) {
            return $of->format($format);
        }
        if (is_numeric($of)) {
            return (new DateTime("@$of"))->format($format);
        }

        if (is_string($of) || $of instanceof Stringable) {
            return (new DateTime((string) $of))->format($format);
        }

        throw RenderingException::notADate($of);
    }

    protected function valueFormat(Configuration $configuration): string
    {
        return $this->format ?? $this->defaultFormat($configuration);
    }

    /**
     * The format when none is set with `format()`: the configured one (`describer.datetime`).
     */
    protected function defaultFormat(Configuration $configuration): string
    {
        return $configuration->datetimeFormat;
    }

    protected function valueType(): ValueType
    {
        return ValueType::Date;
    }
}
