<?php

declare(strict_types=1);

namespace Test\Unit\Http\Rules\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * A native resource whose type is read from its model: unknown without one.
 *
 * @property-read object{type: string} $resource
 */
final class UntypedNativeResource extends JsonApiResource
{
    public function toType(Request $request): string
    {
        return $this->resource->type;
    }
}
