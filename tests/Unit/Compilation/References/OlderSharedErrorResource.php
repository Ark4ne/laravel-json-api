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
 * An error the shapes of `Initial` and `Labels` show, not the head.
 *
 * @extends JsonApiResource<object>
 */
#[JsonApiType('older-shared-error')]
final class OlderSharedErrorResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return ['title' => $this->string()->was(ApiRevision::Initial, name: 'heading')];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'writer' => $this->one(ProfileResource::class, static fn() => null)
                ->was(ApiRevision::Labels, value: $this->one(stdClass::class)),
        ];
    }
}
