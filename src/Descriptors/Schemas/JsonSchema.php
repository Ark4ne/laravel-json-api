<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Schemas;

use Ark4ne\ApiRevisions\Definition\Kind;
use Ark4ne\ApiRevisions\Definition\TypeDefinition;
use Ark4ne\ApiRevisions\Schema\Descriptor;
use Ark4ne\ApiRevisions\Schema\InvalidValue;
use JsonException;

/**
 * A JSON document held as a string (a column without cast), rendered decoded: objects and lists as they are. A value
 * already decoded (an `array` cast) is rendered as it is.
 *
 * A custom type of `ark4ne/laravel-api-revisions` (`json`): a string that is not valid JSON is a value error
 * (rendered `null`).
 *
 * @extends Descriptor<mixed>
 */
final class JsonSchema extends Descriptor
{
    public function normalize(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value ?? new InvalidValue(null, Kind::Custom, null);
        }

        try {
            // A document that is `null` is refused as every `null`: the pipeline renders it for a nullable field only.
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR) ?? new InvalidValue($value, Kind::Custom, null);
        } catch (JsonException) {
            return new InvalidValue($value, Kind::Custom, null);
        }
    }

    protected function type(): TypeDefinition
    {
        // Any JSON value: an empty schema.
        return TypeDefinition::custom('json');
    }
}
