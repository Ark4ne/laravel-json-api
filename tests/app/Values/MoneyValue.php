<?php

declare(strict_types=1);

namespace Test\app\Values;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\AttributeDefinition;
use Ark4ne\JsonApi\Definition\ValueType;
use Ark4ne\JsonApi\Descriptors\Values\Value;
use Illuminate\Http\Request;

/**
 * An amount held in cents, rendered as a decimal string: a value type of the application.
 *
 * @template T
 * @extends Value<T>
 */
final class MoneyValue extends Value
{
    public function value(mixed $of, Request $request, Configuration $configuration): string
    {
        return number_format(is_numeric($of) ? (int) $of / 100 : 0, 2, '.', '');
    }

    public function definition(
        string $name,
        mixed $subject = null,
        ?Configuration $configuration = null,
    ): AttributeDefinition {
        return parent::definition($name, $subject, $configuration)->format('money');
    }

    protected function valueType(): ValueType
    {
        return ValueType::String;
    }
}
