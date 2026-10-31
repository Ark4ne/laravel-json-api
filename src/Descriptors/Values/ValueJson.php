<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Values;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\ValueType;
use Ark4ne\JsonApi\Exceptions\RenderingException;
use Illuminate\Http\Request;
use JsonException;

/**
 * A JSON document held as a string (a column without cast), rendered decoded: objects and lists as they are. A value
 * already decoded (an `array` cast) is rendered as it is.
 *
 * @template T
 * @extends Value<T>
 */
final class ValueJson extends Value
{
    /**
     * @throws RenderingException when the string is not valid JSON
     */
    public function value(mixed $of, Request $request, Configuration $configuration): mixed
    {
        if (!is_string($of)) {
            return $of;
        }

        try {
            return json_decode($of, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw RenderingException::invalidJson(is_string($this->attribute) ? $this->attribute : 'json', $e->getMessage());
        }
    }

    protected function valueType(): ValueType
    {
        return ValueType::Mixed;
    }

    protected function valueFormat(Configuration $configuration): string
    {
        return 'json';
    }
}
