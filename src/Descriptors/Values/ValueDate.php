<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Values;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\AttributeDefinition;
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

    public function definition(
        string $name,
        mixed $subject = null,
        ?Configuration $configuration = null,
    ): AttributeDefinition {
        return parent::definition($name, $subject, $configuration)
            ->format($this->format ?? ($configuration ?? Configuration::defaults())->dateFormat);
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
        if ($of instanceof DateTimeInterface) {
            return $of->format($this->format ?? $configuration->dateFormat);
        }
        if (is_numeric($of)) {
            return (new DateTime("@$of"))->format($this->format ?? $configuration->dateFormat);
        }

        return (new DateTime($of))->format($this->format ?? $configuration->dateFormat);
    }

    protected function valueType(): ValueType
    {
        return ValueType::Date;
    }
}
