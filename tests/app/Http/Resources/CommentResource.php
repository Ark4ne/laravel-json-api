<?php

namespace Test\app\Http\Resources;

use Ark4ne\JsonApi\Resource\JsonApiResource;
use DateTimeInterface;
use Illuminate\Http\Request;

/**
 * @mixin \Test\app\Models\Comment
 */
class CommentResource extends JsonApiResource
{
    protected function toAttributes(Request $request): iterable
    {
        return [
            'content' => $this->content,
        ];
    }

    protected function toResourceMeta(Request $request): ?iterable
    {
        return [
            'created_at' => $this->created_at->format(DateTimeInterface::ATOM),
            'updated_at' => $this->updated_at->format(DateTimeInterface::ATOM),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'user' => fn() => new UserResource($this->user),
            'post' => fn() => new PostResource($this->post),
        ];
    }
}
