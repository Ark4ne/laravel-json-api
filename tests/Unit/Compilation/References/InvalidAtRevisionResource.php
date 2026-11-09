<?php

declare(strict_types=1);

namespace Test\Unit\Compilation\References;

use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use stdClass;
use Test\app\Revisions\ApiRevision;
use Test\app\Revisions\ProfileResource;

/**
 * An error only the shape of `Initial` shows: its relationship there reaches no resource.
 *
 * @extends JsonApiResource<object>
 */
#[JsonApiType('invalid-at-revision')]
final class InvalidAtRevisionResource extends JsonApiResource
{
    protected function toRelationships(Request $request): iterable
    {
        return [
            'author' => $this->one(ProfileResource::class, static fn() => null)
                ->was(ApiRevision::Initial, value: $this->one(stdClass::class)),
        ];
    }
}
