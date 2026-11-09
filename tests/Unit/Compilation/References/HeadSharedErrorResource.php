<?php

declare(strict_types=1);

namespace Test\Unit\Compilation\References;

use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use stdClass;
use Test\app\Revisions\ApiRevision;

/**
 * An error every shape shows, the head and `Initial` alike.
 *
 * @extends JsonApiResource<object>
 */
#[JsonApiType('head-shared-error')]
final class HeadSharedErrorResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return ['title' => $this->string()->was(ApiRevision::Initial, name: 'heading')];
    }

    protected function toRelationships(Request $request): iterable
    {
        return ['author' => $this->one(stdClass::class)];
    }
}
