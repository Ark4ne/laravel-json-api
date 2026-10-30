<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources\Attributes;

use Attribute;

/**
 * The type of the resource, instead of overriding `toType()`: `#[JsonApiType('posts')]`.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class JsonApiType
{
    public function __construct(public string $type) {}
}
