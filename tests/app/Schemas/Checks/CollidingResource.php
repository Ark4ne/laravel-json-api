<?php

declare(strict_types=1);

namespace Test\app\Schemas\Checks;

use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Revisions\ApiRevision;

/**
 * An attribute and a relationship both named `owner` up to `Labels`.
 *
 * @extends JsonApiResource<\Test\app\Models\User>
 */
#[JsonApiType('colliding')]
final class CollidingResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'owner' => Schema::string()->until(ApiRevision::Labels),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'user' => $this->one(ValidResource::class)->was(ApiRevision::Labels, name: 'owner'),
        ];
    }
}
