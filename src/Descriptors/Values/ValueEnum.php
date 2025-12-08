<?php

namespace Ark4ne\JsonApi\Descriptors\Values;

use BackedEnum;
use Illuminate\Http\Request;
use UnitEnum;

/**
 * @template T
 * @template U extends UnitEnum|BackedEnum
 * @extends Value<T>
 */
class ValueEnum extends Value
{
    /**
     * @var class-string<U>|null
     */
    protected null|string $type = null;

    /**
     * Define the type of elements in the array
     *
     * @param class-string<U> $type
     * @return $this<T, U>
     */
    public function of(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    protected function value(mixed $of, Request $request): mixed
    {
        if ($of instanceof BackedEnum) return $of->value;
        if ($of instanceof UnitEnum) return $of->name;

        return $of;
    }
}
