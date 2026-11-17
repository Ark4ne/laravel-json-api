<?php

declare(strict_types=1);

namespace Test\app\Schemas\Checks;

use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Revisions\ApiRevision;

/**
 * An attribute named `type` up to `Labels`, a relationship named `id`: reserved by JSON:API.
 *
 * @extends JsonApiResource<\Test\app\Models\User>
 */
#[JsonApiType('reserved')]
final class ReservedNameResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'kind' => Schema::string()->was(ApiRevision::Labels, name: 'type'),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'id' => $this->one(ValidResource::class),
        ];
    }
}
