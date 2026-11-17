<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Schemas;

use Ark4ne\ApiRevisions\Definition\Kind;
use Ark4ne\ApiRevisions\Definition\TypeDefinition;
use Ark4ne\ApiRevisions\Schema\Descriptor;
use Ark4ne\ApiRevisions\Schema\InvalidValue;
use Illuminate\Contracts\Support\Arrayable;

/**
 * A value of an unknown type, rendered as it is: the escape hatch of a field no type describes.
 *
 * @extends Descriptor<mixed>
 */
final class MixedSchema extends Descriptor
{
    public function normalize(mixed $value): mixed
    {
        // The pipeline of the base handles a `null` before any type: given anyway, it is refused, as its contract says.
        return $value ?? new InvalidValue(null, Kind::Any, null);
    }

    /**
     * Collections and other arrayables become plain arrays, at any depth.
     */
    public function serialize(mixed $value): mixed
    {
        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        }

        return is_array($value) ? array_map($this->serialize(...), $value) : $value;
    }

    protected function type(): TypeDefinition
    {
        return TypeDefinition::of(Kind::Any);
    }
}
