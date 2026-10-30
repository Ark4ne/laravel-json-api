<?php

declare(strict_types=1);

namespace Test\Unit\Compilation\Fixtures;

use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;

/**
 * Declares its type with the attribute.
 *
 * @extends JsonApiResource<mixed>
 */
#[JsonApiType('articles')]
final class AttributeTypedResource extends JsonApiResource {}
