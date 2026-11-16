<?php

declare(strict_types=1);

namespace Benchmark\Package;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use DateTimeInterface;
use Illuminate\Http\Request;

/**
 * @extends JsonApiResource<\Benchmark\Models\Post>
 */
final class PostResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'post';
    }

    public function toAttributes(Request $request): iterable
    {
        return [
            'title' => $this->string(),
            'content' => $this->string(),
        ];
    }

    protected function toResourceMeta(Request $request): iterable
    {
        return [
            'created_at' => $this->datetime()->format(DateTimeInterface::ATOM),
            'updated_at' => $this->datetime()->format(DateTimeInterface::ATOM),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'user' => $this->one(UserResource::class)->withLoad(true),
            'comments' => $this->many(CommentResource::class)->withLoad(true),
        ];
    }
}
