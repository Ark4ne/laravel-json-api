<?php

declare(strict_types=1);

namespace Test\Feature\GuessedResources\Fixtures;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * @extends JsonApiResource<Writer>
 */
final class WriterResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'writer';
    }

    public function toAttributes(Request $request): iterable
    {
        return ['name' => $this->string()];
    }
}
