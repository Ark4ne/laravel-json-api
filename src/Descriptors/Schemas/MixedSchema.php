<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Schemas;

use Ark4ne\ApiRevisions\Definition\Kind;
use Ark4ne\ApiRevisions\Definition\TypeDefinition;
use Ark4ne\ApiRevisions\Schema\Descriptor;
use Ark4ne\ApiRevisions\Schema\InvalidValue;

/**
 * A value of an unknown type, rendered as it is: the escape hatch of a field no type describes.
 *
 * @extends Descriptor<mixed>
 */
final class MixedSchema extends Descriptor
{
    public function normalize(mixed $value): mixed
    {
        return $value ?? new InvalidValue(null, Kind::Any, null);
    }

    protected function type(): TypeDefinition
    {
        return TypeDefinition::of(Kind::Any);
    }
}
