<?php

declare(strict_types=1);

namespace Test\Unit\Compilation\References;

use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Revisions\TeacherResource;

/**
 * Declares no revision, and relates to resources of two enums both having a case `Labels`: `teacher` exists at
 * `ApiRevision::Labels`, `old` does not at `OtherRevision::Labels`.
 *
 * @extends JsonApiResource<object>
 */
#[JsonApiType('two-enums')]
final class TwoEnumsReferencesResource extends JsonApiResource
{
    protected function toRelationships(Request $request): iterable
    {
        return [
            'teacher' => $this->one(TeacherResource::class, static fn() => null)->whenIncluded(),
            'old' => $this->one(OtherOldResource::class, static fn() => null)->whenIncluded(),
        ];
    }
}
