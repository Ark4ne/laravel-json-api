<?php

declare(strict_types=1);

namespace Test\app\Revisions;

use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Test\app\Http\Resources\CommentResource;
use Test\app\Models\User;

/**
 * A user through three revisions (Initial → Labels → UsersReplaceTeachers): every kind of declaration.
 *
 * @extends JsonApiResource<User>
 */
#[JsonApiType('profiles')]
final class ProfileResource extends JsonApiResource
{
    /** How many times the attributes were described. */
    public static int $reads = 0;

    public function toAttributes(Request $request): iterable
    {
        self::$reads++;

        return [
            'display_name' => $this->string('name')
                ->was(ApiRevision::Labels, name: 'label')
                ->was(ApiRevision::Initial, name: 'name'),
            'email' => $this->string(),
            $this->applyWhen(fn() => true, [
                'tag_names' => $this->string(fn() => 'tags')->since(ApiRevision::UsersReplaceTeachers),
            ]),
            // Revisions
            'full_name' => $this->string(fn() => $this->resource->name)->until(ApiRevision::Labels),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'remarks' => $this->many(CommentResource::class, 'comments')
                ->withLoad(['comments' => fn(Builder|Relation $query) => $query->where('content', 'like', '%e%')])
                ->was(ApiRevision::Initial, name: 'comments'),
        ];
    }
}
