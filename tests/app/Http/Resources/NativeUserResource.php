<?php

declare(strict_types=1);

namespace Test\app\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * A user of Laravel's native JSON:API, related to by `NativePostResource`.
 *
 * @property-read \Test\app\Models\User $resource
 */
final class NativeUserResource extends JsonApiResource
{
    public function toType(Request $request): string
    {
        return 'users';
    }

    /**
     * @return list<string>
     */
    public function toAttributes(Request $request): array
    {
        return ['name', 'email'];
    }

    /**
     * Never rendered under a resource of the package: a native resource is a leaf.
     *
     * @return list<string>
     */
    public function toRelationships(Request $request): array
    {
        return ['posts'];
    }

    /**
     * @return array<string, string>
     */
    public function toLinks(Request $request): array
    {
        return ['self' => "https://api.example.com/users/{$this->resource->id}"];
    }

    /**
     * @return array<string, bool>
     */
    public function toMeta(Request $request): array
    {
        return ['native' => true];
    }
}
