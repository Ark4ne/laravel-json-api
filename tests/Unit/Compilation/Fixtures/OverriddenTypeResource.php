<?php

declare(strict_types=1);

namespace Test\Unit\Compilation\Fixtures;

use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * Declares its type with the attribute and with `toType()`: `toType()` wins.
 *
 * @extends JsonApiResource<mixed>
 */
#[JsonApiType('articles')]
final class OverriddenTypeResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'stories';
    }
}
