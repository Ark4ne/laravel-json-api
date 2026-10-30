<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Values;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\ValueType;
use Illuminate\Http\Request;

/**
 * @template T
 * @extends Value<T>
 */
class ValueBool extends Value
{
    public function value(mixed $of, Request $request, Configuration $configuration): bool
    {
        return (bool) $of;
    }

    protected function valueType(): ValueType
    {
        return ValueType::Boolean;
    }
}
