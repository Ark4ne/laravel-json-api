<?php

declare(strict_types=1);

namespace Benchmark\Schema;

use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\JsonApi\Descriptors\Schemas\RelationshipSchema;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * @extends JsonApiResource<\Benchmark\Models\Comment>
 */
final class CommentResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'comment';
    }

    public function toAttributes(Request $request): iterable
    {
        return [
            'content' => Schema::string(),
        ];
    }

    /**
     * @return iterable<string, RelationshipSchema<\Benchmark\Models\Comment>>
     */
    protected function toRelationships(Request $request): iterable
    {
        return [
            'user' => Schema::relationship($this->one(UserResource::class)->withLoad(true)),
        ];
    }
}
