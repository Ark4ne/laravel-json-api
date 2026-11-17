<?php

declare(strict_types=1);

namespace Test\app\Schemas\Checks;

use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Revisions\ApiRevision;
use Test\app\Revisions\MemberResource;
use Test\app\Revisions\TeacherResource;

/**
 * Nothing to report: distinct names at every revision, a relationship to the resource existing at each one.
 *
 * @extends JsonApiResource<\Test\app\Models\User>
 */
#[JsonApiType('valid')]
final class ValidResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'name' => Schema::string()->was(ApiRevision::Initial, name: 'label'),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'member' => $this->one(MemberResource::class)
                ->was(ApiRevision::Labels, name: 'teacher', schema: $this->one(TeacherResource::class)),
        ];
    }
}
