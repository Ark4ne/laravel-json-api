<?php

namespace Ark4ne\JsonApi\Descriptors\Values;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * @template T
 * @template U
 * @extends Value<T>
 */
class ValueArray extends Value
{
    /**
     * @var class-string<Value<U>>|Value<U>|null
     */
    protected null|string|Value $type = null;

    /**
     * Define the type of elements in the array
     *
     * @param class-string<Value<U>>|Value<U> $type
     * @return $this<T, U>
     */
    public function of(string|Value $type): static
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @param mixed $of
     * @param Request $request
     * @return array<array-key, mixed>
     */
    public function value(mixed $of, Request $request): array
    {
        if (!$this->type) {
            return (new Collection($of))->toArray();
        }

        $type = is_string($this->type)
            ? new ($this->type)(null)
            : $this->type;

        return (new Collection($of))
            ->map(fn($item) => $type->value($item, $request))
            ->toArray();
    }
}
