<?php

declare(strict_types=1);

namespace Benchmark\Package;

use Ark4ne\JsonApi\Descriptors\Relations\RelationMany;
use Ark4ne\JsonApi\Descriptors\Relations\RelationOne;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use DateTimeInterface;
use Illuminate\Http\Request;

/**
 * Written with the API common to 1.9 and 2.0, to render the same document with both.
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
            'title' => $this->string(),
            'content' => $this->string(),
        ];
    }

    protected function toResourceMeta(Request $request): iterable
    {
        return [
            'created_at' => $this->date()->format(DateTimeInterface::ATOM),
            'updated_at' => $this->date()->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return iterable<string, RelationOne<mixed>|RelationMany<mixed>>
     */
    protected function toRelationships(Request $request): iterable
    {
        return [
            'user' => $this->one(UserResource::class)->withLoad(true),
            'comments' => $this->many(CommentResource::class)->withLoad(true),
        ];
    }
}
