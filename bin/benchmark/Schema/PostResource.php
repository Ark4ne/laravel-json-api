<?php

declare(strict_types=1);

namespace Benchmark\Schema;

use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\JsonApi\Descriptors\Schemas\RelationshipSchema;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use DateTimeInterface;
use Illuminate\Http\Request;

/**
 * The post of `Package\PostResource` declared with the descriptors of `ark4ne/laravel-api-revisions`: the same
 * document, rendered by the base.
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
            'title' => Schema::string(),
            'content' => Schema::string(),
        ];
    }

    protected function toResourceMeta(Request $request): iterable
    {
        return [
            'created_at' => Schema::dateTime()->format(DateTimeInterface::ATOM),
            'updated_at' => Schema::dateTime()->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return iterable<string, RelationshipSchema<\Benchmark\Models\Post>>
     */
    protected function toRelationships(Request $request): iterable
    {
        return [
            'user' => Schema::relationship($this->one(UserResource::class)->withLoad(true)),
            'comments' => Schema::relationship($this->many(CommentResource::class)->withLoad(true)),
        ];
    }
}
