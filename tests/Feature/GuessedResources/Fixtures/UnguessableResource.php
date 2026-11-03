<?php

declare(strict_types=1);

namespace Test\Feature\GuessedResources\Fixtures;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * Its model follows no convention and is not declared: its relationships need their resource class.
 *
 * @extends JsonApiResource<array<string, mixed>>
 */
final class UnguessableResource extends JsonApiResource
{
    /**
     * @return iterable<string, \Ark4ne\JsonApi\Descriptors\Relations\Relation<array<string, mixed>>>
     */
    protected function toRelationships(Request $request): iterable
    {
        return ['user' => $this->one()];
    }
}
