<?php

declare(strict_types=1);

namespace Test\Unit\Compilation\Fixtures;

use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Revisions\ApiRevision;

/**
 * A history the check of `ark4ne/laravel-api-revisions` refuses: no conversion from a boolean to a date.
 *
 * @extends JsonApiResource<array<string, mixed>>
 */
#[JsonApiType('inconsistent')]
final class InconsistentHistoryResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'code' => $this->bool()->was(ApiRevision::Initial, schema: $this->date()),
        ];
    }
}
