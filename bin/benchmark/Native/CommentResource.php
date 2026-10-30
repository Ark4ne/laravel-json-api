<?php

declare(strict_types=1);

namespace Benchmark\Native;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @property \Benchmark\Models\Comment $resource
 */
final class CommentResource extends JsonApiResource
{
    public function toType(Request $request): string
    {
        return 'comment';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
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
        ];
    }
}
