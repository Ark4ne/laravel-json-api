<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Values;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\AttributeDefinition;
use Ark4ne\JsonApi\Definition\ValueType;
use Ark4ne\JsonApi\Descriptors\ValueTypes;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use JsonSerializable;
use Traversable;

/**
 * @template T
 * @template U
 * @extends Value<T>
 */
final class ValueArray extends Value
{
    /**
     * @var string|Value<*>|null The descriptor of the items, whatever the model it was written for
     */
    protected string|Value|null $type = null;

    /**
     * Define the type of elements in the array: a descriptor, or a value type by its name (`'integer'`, a registered
     * `'money'`) or its class, resolved against the configuration
     *
     * @param string|Value<*> $type
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
            return self::items($of)->toArray();
        }

        return self::items($of)
            ->map(fn($item) => $type->value($item, $request, $configuration))
            ->toArray();
    }

    /**
     * The items of the raw value, as Laravel's `Collection` reads them: the items of a collection, an arrayable or an iterable, the result
     * of a `JsonSerializable`, the properties of another object, a scalar as the only item, `null` as none.
     *
     * @return Collection<array-key, mixed>
     */
    private static function items(mixed $of): Collection
    {
        return new Collection(match (true) {
            $of === null => [],
            is_array($of) => $of,
            $of instanceof Enumerable => $of->all(),
            $of instanceof Arrayable => $of->toArray(),
            $of instanceof Traversable => iterator_to_array($of),
            $of instanceof JsonSerializable => (array) $of->jsonSerialize(),
            is_object($of) => get_object_vars($of),
            default => [$of],
        });
    }

    protected function valueType(): ValueType
    {
        return ValueType::Array;
    }

    /**
     * @return Value<*>|null
     */
    private function itemType(Configuration $configuration): ?Value
    {
        return is_string($this->type) ? ValueTypes::make($configuration, $this->type) : $this->type;
    }
}
