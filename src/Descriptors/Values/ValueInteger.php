<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Values;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\ValueType;
use Ark4ne\JsonApi\Exceptions\RenderingException;
use Illuminate\Http\Request;

/**
 * @template T
 * @extends Value<T>
 */
final class ValueInteger extends Value
{
    public function value(mixed $of, Request $request, Configuration $configuration): int
    {
        return is_scalar($of) || $of === null ? (int) $of : throw RenderingException::notCastable($of, 'an integer');
    }

    protected function valueType(): ValueType
    {
        return ValueType::Integer;
    }
}
