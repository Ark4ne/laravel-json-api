<?php

declare(strict_types=1);

namespace Test\Unit\Compilation\References;

use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Revisions\MemberResource;

/**
 * A relationship to a resource `#[JsonApiSince(UsersReplaceTeachers)]` without a `was()` for the older revisions.
 *
 * @extends JsonApiResource<object>
 */
#[JsonApiType('forgotten')]
final class ForgottenWasResource extends JsonApiResource
{
    protected function toRelationships(Request $request): iterable
    {
        return [
            'members' => $this->many(MemberResource::class, static fn() => [])->whenIncluded(),
        ];
    }
}
