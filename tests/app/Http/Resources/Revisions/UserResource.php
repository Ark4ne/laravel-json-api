<?php

declare(strict_types=1);

namespace Test\app\Http\Resources\Revisions;

use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Models\User;
use Test\app\Revisions\ApiRevision;

/**
 * The users of the revisions scenario (docs/rfc/versioned-resources-epics.md EV4): `display_name` ← `label` (`Labels`)
 * ← `name` (`Initial`), `born_at` a date-time ← a date (`Initial`), `full_name` until `Labels`.
 *
 * @extends JsonApiResource<User>
 */
#[JsonApiType('users')]
final class UserResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'display_name' => $this->string('name')
                ->was(ApiRevision::Labels, name: 'label')
                ->was(ApiRevision::Initial, name: 'name'),
            'email' => $this->string(),
            'born_at' => $this->datetime('created_at')
                ->was(ApiRevision::Initial, value: $this->date()),
            // Revisions
            'full_name' => $this->string(fn() => "{$this->resource->name} <{$this->resource->email}>")
                ->until(ApiRevision::Labels),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'posts' => $this->many(PostResource::class)->whenIncluded(),
            'comments' => $this->many(CommentResource::class)->whenIncluded(),
        ];
    }

    protected function toResourceMeta(Request $request): iterable
    {
        return [
            'updated_at' => $this->datetime()->was(ApiRevision::Labels, name: 'modified_at'),
        ];
    }

    protected function toDocumentMeta(Request $request): iterable
    {
        return [
            'directory' => $this->string(fn() => 'users')->was(ApiRevision::Labels, name: 'catalog'),
        ];
    }
}
