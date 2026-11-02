<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Values;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\ValueType;
use Ark4ne\JsonApi\Exceptions\RenderingException;
use Illuminate\Http\Request;
use Stringable;

/**
 * @template T
 * @extends Value<T>
 */
final class ValueString extends Value
{
    public function value(mixed $of, Request $request, Configuration $configuration): string
    {
        return is_scalar($of) || $of === null || $of instanceof Stringable
            ? (string) $of
            : throw RenderingException::notCastable($of, 'a string');
    }

    protected function valueType(): ValueType
    {
        return ValueType::String;
    }
}
