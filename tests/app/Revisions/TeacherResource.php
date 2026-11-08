<?php

declare(strict_types=1);

namespace Test\app\Revisions;

use Ark4ne\JsonApi\Definition\JsonApiUntil;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Models\User;

/**
 * An old type, kept for the clients up to `Labels`: users replace teachers. Not final, to check that a subclass does
 * not inherit its lifetime.
 *
 * @extends JsonApiResource<User>
 */
#[JsonApiType('teachers')]
#[JsonApiUntil(ApiRevision::Labels)]
class TeacherResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'name' => $this->string(),
        ];
    }
}
