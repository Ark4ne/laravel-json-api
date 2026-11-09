<?php

declare(strict_types=1);

namespace Benchmark\Revised;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use DateTimeInterface;
use Illuminate\Http\Request;

/**
 * The post of the benchmark, with revisions: the same document as `Package\PostResource` at the head, renamed members
 * for older clients.
 *
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
            'title' => $this->string()->was(Revision::Oldest, name: 'headline'),
            'content' => $this->string(),
        ];
    }

    protected function toResourceMeta(Request $request): iterable
    {
        return [
            'created_at' => $this->date()->format(DateTimeInterface::ATOM),
            'updated_at' => $this->date()->format(DateTimeInterface::ATOM)->was(Revision::Oldest, name: 'modified_at'),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'user' => $this->one(UserResource::class)->withLoad(true)->was(Revision::Middle, name: 'author'),
            'comments' => $this->many(CommentResource::class)->withLoad(true),
        ];
    }
}
