<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Values;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\ValueType;
use Illuminate\Http\Request;
use Stringable;

/**
 * a ULID (`Symfony\\Component\\Uid\\Ulid`, `Illuminate\\Support\\Str::ulid()`, a string), rendered as a string of format `ulid`.
 *
 * @template T
 * @extends Value<T>
 */
final class ValueUlid extends Value
{
    public function value(mixed $of, Request $request, Configuration $configuration): string
    {
        return is_scalar($of) || $of instanceof Stringable ? (string) $of : '';
    }

    protected function valueType(): ValueType
    {
        return ValueType::String;
    }

    protected function valueFormat(Configuration $configuration): string
    {
        return 'ulid';
    }
}
