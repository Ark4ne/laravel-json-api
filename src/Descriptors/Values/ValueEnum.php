<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Values;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\AttributeDefinition;
use Ark4ne\JsonApi\Definition\ValueType;
use BackedEnum;
use Illuminate\Http\Request;
use UnitEnum;

/**
 * @template T
 * @template U of UnitEnum
 * @extends Value<T>
 */
class ValueEnum extends Value
{
    /**
     * @var class-string<U>|null
     */
    protected ?string $type = null;

    /**
     * Define the type of elements in the array
     *
     * @param class-string<U> $type
     * @return $this<T, U>
     */
    public function of(string $type): static
    {
        return $this->with('type', $type);
    }

    public function definition(
        string $name,
        mixed $subject = null,
        ?Configuration $configuration = null,
    ): AttributeDefinition {
        return parent::definition($name, $subject, $configuration)->enum($this->type);
    }

    protected function value(mixed $of, Request $request, Configuration $configuration): mixed
    {
        if ($of instanceof BackedEnum) {
            return $of->value;
        }
        if ($of instanceof UnitEnum) {
            return $of->name;
        }

        return $of;
    }

    protected function valueType(): ValueType
    {
        return ValueType::Enum;
    }
}
