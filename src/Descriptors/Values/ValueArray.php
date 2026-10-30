<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Values;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\AttributeDefinition;
use Ark4ne\JsonApi\Definition\ValueType;
use Ark4ne\JsonApi\Descriptors\ValueTypes;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
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
     * @var string|Value<U>|null
     */
    protected string|Value|null $type = null;

    /**
     * Define the type of elements in the array: a descriptor, or a value type by its name (`'integer'`, a registered
     * `'money'`) or its class, resolved against the configuration
     *
     * @param string|class-string<Value<U>>|Value<U> $type
     * @return static
     *
     * @throws DefinitionException when a class is given that needs more than an attribute (a struct)
     */
    public function of(string|Value $type): static
    {
        if (is_string($type) && class_exists($type)) {
            // Fails now rather than on rendering: a struct needs its closure.
            ValueTypes::make(Configuration::defaults(), $type);
        }

        return $this->with('type', $type);
    }

    public function definition(
        string $name,
        mixed $subject = null,
        ?Configuration $configuration = null,
    ): AttributeDefinition {
        $configuration ??= Configuration::defaults();

        return parent::definition($name, $subject, $configuration)
            ->items($this->itemType($configuration)?->definition($name, $subject, $configuration));
    }

    /**
     * @param mixed $of
     * @param Request $request
     * @return array<array-key, mixed>
     */
    public function value(mixed $of, Request $request, Configuration $configuration): array
    {
        $type = $this->itemType($configuration);

        if ($type === null) {
            return (new Collection($of))->toArray();
        }

        return (new Collection($of))
            ->map(fn($item) => $type->value($item, $request, $configuration))
            ->toArray();
    }

    protected function valueType(): ValueType
    {
        return ValueType::Array;
    }

    /**
     * @return Value<mixed>|null
     */
    private function itemType(Configuration $configuration): ?Value
    {
        return is_string($this->type) ? ValueTypes::make($configuration, $this->type) : $this->type;
    }
}
