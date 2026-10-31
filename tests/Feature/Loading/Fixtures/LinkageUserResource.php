<?php

declare(strict_types=1);

namespace Test\Feature\Loading\Fixtures;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

/**
 * A user whose posts are constrained to even ids, and whose comments render only when loaded.
 *
 * @extends JsonApiResource<\Test\app\Models\User>
 */
final class LinkageUserResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'user';
    }

    public function toAttributes(Request $request): iterable
    {
        return [
            'name' => $this->string(),
        ];
    }

    /**
     * @return iterable<string, \Ark4ne\JsonApi\Descriptors\Relations\Relation<\Test\app\Models\User>>
     */
    protected function toRelationships(Request $request): iterable
    {
        return [
            'posts' => $this->many(LinkagePostResource::class)
                ->withLoad(['posts' => static fn(HasMany $query) => $query->getQuery()->whereKey(range(2, 100, 2))]),
            'comments' => $this->many(LinkageCommentResource::class)->whenLoaded(),
        ];
    }
}
