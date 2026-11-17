<?php

declare(strict_types=1);

namespace Benchmark\Legacy;

use Ark4ne\JsonApi\Descriptors\Relations\RelationMany;
use Ark4ne\JsonApi\Descriptors\Relations\RelationOne;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use DateTimeInterface;
use Illuminate\Http\Request;

/**
 * Written with the API of 1.9, rendered by its copy: the document of `Package\PostResource`.
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
     * @return iterable<string, RelationOne<\Benchmark\Models\Post>|RelationMany<\Benchmark\Models\Post>>
     */
    protected function toRelationships(Request $request): iterable
    {
        return [
            'user' => $this->one(UserResource::class)->withLoad(true),
            'comments' => $this->many(CommentResource::class)->withLoad(true),
        ];
    }
}
