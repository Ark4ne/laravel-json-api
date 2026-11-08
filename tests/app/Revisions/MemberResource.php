<?php

declare(strict_types=1);

namespace Test\app\Revisions;

use Ark4ne\JsonApi\Definition\JsonApiSince;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Models\User;

/**
 * A new type, from `UsersReplaceTeachers`.
 *
 * @extends JsonApiResource<User>
 */
#[JsonApiType('members')]
#[JsonApiSince(ApiRevision::UsersReplaceTeachers)]
final class MemberResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'name' => $this->string(),
        ];
    }
}
