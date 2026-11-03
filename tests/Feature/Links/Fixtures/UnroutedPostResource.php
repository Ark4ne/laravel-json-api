<?php

declare(strict_types=1);

namespace Test\Feature\Links\Fixtures;

use Ark4ne\JsonApi\Resources\Attributes\JsonApiRoutes;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * @extends JsonApiResource<\Test\app\Models\Post>
 */
#[JsonApiRoutes(self: 'nowhere.show')]
final class UnroutedPostResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return ['title' => $this->string()];
    }
}
