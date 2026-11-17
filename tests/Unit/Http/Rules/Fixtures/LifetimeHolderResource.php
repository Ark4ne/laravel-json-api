<?php

declare(strict_types=1);

namespace Test\Unit\Http\Rules\Fixtures;

use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Models\Comment;
use Test\app\Models\User;
use Test\app\Revisions\MemberResource;
use Test\app\Revisions\TeacherResource;

/**
 * Relationships to resources living only part of the revisions: `members` from `UsersReplaceTeachers`, `teachers` up
 * to `Labels`, `people` polymorphic over both.
 *
 * @extends JsonApiResource<object>
 */
#[JsonApiType('holders')]
final class LifetimeHolderResource extends JsonApiResource
{
    protected function toRelationships(Request $request): iterable
    {
        return [
            'members' => $this->many(MemberResource::class, static fn() => [])->whenIncluded(),
            'teachers' => $this->many(TeacherResource::class, static fn() => [])->whenIncluded(),
            'people' => $this->one(MemberResource::class, static fn() => null)
                ->morphs([User::class => MemberResource::class, Comment::class => TeacherResource::class])
                ->whenIncluded(),
        ];
    }
}
