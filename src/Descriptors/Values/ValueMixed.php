<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Values;

use Ark4ne\JsonApi\Configuration\Configuration;
use Illuminate\Http\Request;

/**
 * @template T
 * @extends Value<T>
 */
final class ValueMixed extends Value
{
    public function value(mixed $of, Request $request, Configuration $configuration): mixed
    {
        return $of;
    }
}
