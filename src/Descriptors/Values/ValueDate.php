<?php

namespace Ark4ne\JsonApi\Descriptors\Values;

use Ark4ne\JsonApi\Support\Config;
use Closure;
use DateTime;
use DateTimeInterface;

/**
 * @template T
 * @extends Value<T>
 */
class ValueDate extends Value
{
    protected string $format;

    public function __construct(string|Closure|null $attribute)
    {
        parent::__construct($attribute);

        $this->format = Config::$date;
    }

    public function format(string $format): static
    {
        $this->format = $format;
        return $this;
    }

    /**
     * @throws \Exception
     */
    public function value(mixed $of): string
    {
        if ($of === null) {
            return (new DateTime("@0"))->format($this->format);
        }
        if ($of instanceof DateTimeInterface) {
            return $of->format($this->format);
        }
        if (is_numeric($of)) {
            return (new DateTime("@$of"))->format($this->format);
        }

        return (new DateTime($of))->format($this->format);
    }
}
