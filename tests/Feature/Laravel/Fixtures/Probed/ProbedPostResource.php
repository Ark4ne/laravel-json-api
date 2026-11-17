<?php

declare(strict_types=1);

namespace Test\Feature\Laravel\Fixtures\Probed;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Models\Post;

/**
 * @extends JsonApiResource<Post>
 */
final class ProbedPostResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'posts';
    }

    public function toAttributes(Request $request): iterable
    {
        ProbedHooks::record(self::class . '::toAttributes', $this->resource);

        return [
            'title' => $this->string(),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        ProbedHooks::record(self::class . '::toRelationships', $this->resource);

        return [
            'user' => $this->one(ProbedUserResource::class)->withLoad(true),
        ];
    }
}
