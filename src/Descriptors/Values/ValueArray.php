<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Values;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\AttributeDefinition;
use Ark4ne\JsonApi\Definition\ValueType;
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
    protected string|Value|null $type = null;

    /**
     * Define the type of elements in the array
     *
     * @param class-string<Value<U>>|Value<U> $type
     * @return $this<T, U>
     */
    public function of(string|Value $type): static
    {
        return $this->with('type', $type);
    }

    public function definition(
        string $name,
        mixed $subject = null,
        ?Configuration $configuration = null,
    ): AttributeDefinition {
        $type = is_string($this->type) ? new ($this->type)(null) : $this->type;

        return parent::definition($name, $subject, $configuration)
            ->items($type?->definition($name, $subject, $configuration));
    }

    /**
     * @param mixed $of
     * @param Request $request
     * @return array<array-key, mixed>
     */
    public function value(mixed $of, Request $request, Configuration $configuration): array
    {
        if (!$this->type) {
            return (new Collection($of))->toArray();
        }

        $type = is_string($this->type)
            ? new ($this->type)(null)
            : $this->type;

        return (new Collection($of))
            ->map(fn($item) => $type->value($item, $request, $configuration))
            ->toArray();
    }

    protected function valueType(): ValueType
    {
        return ValueType::Array;
    }
}
