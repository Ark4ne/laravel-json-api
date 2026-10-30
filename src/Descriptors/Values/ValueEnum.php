<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Values;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\AttributeDefinition;
use Ark4ne\JsonApi\Definition\ValueType;
use Ark4ne\JsonApi\Exceptions\RenderingException;
use BackedEnum;
use Illuminate\Http\Request;
use ReflectionEnum;
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
     * The enum the values are cases of: a raw value is cast through it (the backed value, or the case name of a pure
     * enum), and anything that is not one of its cases throws a `RenderingException`.
     *
     * @param class-string<U> $type
     * @return static
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
        if ($of === null) {
            return null;
        }

        if ($this->type !== null && !$of instanceof $this->type) {
            $of = self::caseOf($this->type, $of) ?? throw RenderingException::notAnEnumCase($of, $this->type);
        }

        if ($of instanceof BackedEnum) {
            return $of->value;
        }
        if ($of instanceof UnitEnum) {
            return $of->name;
        }

        return $of;
    }

    /**
     * The case of `$type` a raw value stands for: its backed value (a numeric string for an integer backing), or its
     * name for a pure enum.
     *
     * @param class-string<UnitEnum> $type
     */
    private static function caseOf(string $type, mixed $value): ?UnitEnum
    {
        if (!is_int($value) && !is_string($value)) {
            return null;
        }

        if (!is_subclass_of($type, BackedEnum::class)) {
            $case = is_string($value) && defined("{$type}::{$value}") ? constant("{$type}::{$value}") : null;

            return $case instanceof $type ? $case : null;
        }

        if ((string) (new ReflectionEnum($type))->getBackingType() === 'int') {
            return is_int($value) || preg_match('/^-?\d+$/', $value) === 1 ? $type::tryFrom((int) $value) : null;
        }

        return $type::tryFrom((string) $value);
    }

    protected function valueType(): ValueType
    {
        return ValueType::Enum;
    }
}
