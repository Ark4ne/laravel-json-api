<?php

declare(strict_types=1);

namespace Test\app\Revisions;

use Ark4ne\JsonApi\Laravel\JsonApi;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Models\User;

/**
 * A change of meaning without change of shape (case 12): up to Labels, `greeting` keeps its old content.
 *
 * @extends JsonApiResource<User>
 */
#[JsonApiType('greeted-users')]
final class GuardedUserResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'greeting' => $this->string(fn() => $this->requestedRevision()->atMost(ApiRevision::Labels)
                ? "Hello {$this->resource->name}"
                : "Welcome back, {$this->resource->name}"),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function with($request): array
    {
        return ['meta' => ['legacy' => JsonApi::revision($request)->atMost(ApiRevision::Labels)]];
    }
}
