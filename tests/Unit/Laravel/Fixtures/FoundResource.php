<?php

declare(strict_types=1);

namespace Test\Unit\Laravel\Fixtures;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * @extends JsonApiResource<\Test\app\Models\Post>
 */
final class FoundResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'found';
    }
}
