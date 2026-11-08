<?php

declare(strict_types=1);

namespace Test\Unit\Compilation\References;

use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Revisions\TeacherResource;

/**
 * A head relationship still reaching a resource `#[JsonApiUntil(Labels)]`.
 *
 * @extends JsonApiResource<object>
 */
#[JsonApiType('stale')]
final class StaleHeadResource extends JsonApiResource
{
    protected function toRelationships(Request $request): iterable
    {
        return [
            'teacher' => $this->one(TeacherResource::class, static fn() => null)->whenIncluded(),
        ];
    }
}
