<?php

declare(strict_types=1);

namespace Test\app\Http\Resources\Revisions;

use Ark4ne\JsonApi\Definition\JsonApiUntil;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Models\User;
use Test\app\Revisions\ApiRevision;

/**
 * A user as the author of a comment, up to `Labels`: replaced by `users`.
 *
 * @extends JsonApiResource<User>
 */
#[JsonApiType('commenters')]
#[JsonApiUntil(ApiRevision::Labels)]
final class CommenterResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'name' => $this->string(),
        ];
    }
}
