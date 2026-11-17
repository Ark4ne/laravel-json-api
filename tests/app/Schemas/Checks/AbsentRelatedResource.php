<?php

declare(strict_types=1);

namespace Test\app\Schemas\Checks;

use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Revisions\MemberResource;

/**
 * A relationship to `members`, a type added at `UsersReplaceTeachers`, without `was()` for the older revisions.
 *
 * @extends JsonApiResource<\Test\app\Models\User>
 */
#[JsonApiType('absent-related')]
final class AbsentRelatedResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'name' => Schema::string(),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'member' => $this->one(MemberResource::class),
        ];
    }
}
