<?php

declare(strict_types=1);

namespace Benchmark\Native;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * The same post with the JSON:API resources of Laravel.
 *
 * @property \Benchmark\Models\Post $resource
 */
final class PostResource extends JsonApiResource
{
    public function toType(Request $request): string
    {
        return 'post';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'title' => $this->resource->title,
            'content' => $this->resource->content,
        ];
    }

    /**
     * @return array<string, class-string<JsonApiResource>>
     */
    public function toRelationships(Request $request): array
    {
        return [
            'user' => UserResource::class,
            'comments' => CommentResource::class,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function toMeta(Request $request): array
    {
        return [
            'created_at' => $this->resource->created_at->format(DateTimeInterface::ATOM),
            'updated_at' => $this->resource->updated_at->format(DateTimeInterface::ATOM),
        ];
    }
}
