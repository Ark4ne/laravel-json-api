<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Values;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\ValueType;
use DateTime;
use DateTimeInterface;
use Exception;
use Illuminate\Http\Request;

/**
 * @template T
 * @extends Value<T>
 */
class ValueDate extends Value
{
    /** `null` until set: the configuration decides (`describer.date`). */
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

        return (new DateTime($of))->format($format);
    }

    protected function valueFormat(Configuration $configuration): string
    {
        return $this->format ?? $this->defaultFormat($configuration);
    }

    /**
     * The format when none is set with `format()`: the configured one (`describer.date`).
     */
    protected function defaultFormat(Configuration $configuration): string
    {
        return $configuration->dateFormat;
    }

    protected function valueType(): ValueType
    {
        return ValueType::Date;
    }
}
