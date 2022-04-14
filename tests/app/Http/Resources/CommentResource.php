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
            'user' => fn() => UserResource::make($this->whenIncluded($request, 'user', $this->user))
                ->asRelationship([
                    'self' => "https://api.example.com/comment/{$this->id}/relationships/user",
                    'related' => "https://api.example.com/comment/{$this->id}/user",
                ]),
            'post' => fn() => PostResource::make($this->whenIncluded($request, 'post', $this->post))
                ->asRelationship([
                    'self' => "https://api.example.com/comment/{$this->id}/relationships/post",
                    'related' => "https://api.example.com/comment/{$this->id}/post",
                ]),
        ];
    }
}
