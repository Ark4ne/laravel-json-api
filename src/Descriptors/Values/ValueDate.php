<?php

namespace Ark4ne\JsonApi\Descriptors\Values;

use DateTime;
use DateTimeInterface;

class ValueDate extends Value
{
    protected string $format = DateTimeInterface::ATOM;

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
        if($of === null) {
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
