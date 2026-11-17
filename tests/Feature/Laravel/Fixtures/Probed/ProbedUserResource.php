<?php

declare(strict_types=1);

namespace Test\Feature\Laravel\Fixtures\Probed;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Models\User;

/**
 * @extends JsonApiResource<User>
 */
final class ProbedUserResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'users';
    }

    public function toAttributes(Request $request): iterable
    {
        ProbedHooks::record(self::class . '::toAttributes', $this->resource);

        return [
            'name' => $this->string(),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        ProbedHooks::record(self::class . '::toRelationships', $this->resource);

        return [
            'posts' => $this->many(ProbedPostResource::class)->whenIncluded()->withLoad(true),
        ];
    }
}
