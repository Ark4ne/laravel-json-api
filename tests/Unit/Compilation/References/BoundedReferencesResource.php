<?php

declare(strict_types=1);

namespace Test\Unit\Compilation\References;

use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Revisions\ApiRevision;
use Test\app\Revisions\MemberResource;
use Test\app\Revisions\TeacherResource;

/**
 * Relationships to resources living part of the revisions, each bounded to them: no diagnostic.
 *
 * @extends JsonApiResource<object>
 */
#[JsonApiType('bounded')]
final class BoundedReferencesResource extends JsonApiResource
{
    protected function toRelationships(Request $request): iterable
    {
        return [
            'member' => $this->one(MemberResource::class, static fn() => null)
                ->whenIncluded()
                ->was(ApiRevision::Labels, name: 'teacher', schema: $this->one(TeacherResource::class, static fn() => null)->whenIncluded()),
            'mentor' => $this->one(TeacherResource::class, static fn() => null)->whenIncluded()->until(ApiRevision::Labels),
            'members' => $this->many(MemberResource::class, static fn() => [])->whenIncluded()->since(ApiRevision::UsersReplaceTeachers),
        ];
    }
}
