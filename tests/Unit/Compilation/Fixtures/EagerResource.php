<?php

declare(strict_types=1);

namespace Test\Unit\Compilation\Fixtures;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * Attributes read eagerly from the model (1.x style): untyped, with the probe path as source.
 *
 * @extends JsonApiResource<\Test\app\Models\Post>
 */
final class EagerResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'eager';
    }

    public function toAttributes(Request $request): iterable
    {
        return [
            'title' => $this->resource->title,
            'author-name' => $this->resource->user->name,
        ];
    }
}
