<?php

declare(strict_types=1);

namespace Test\Feature\Loading\Fixtures;

use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * A post whose relationships are read from the relations of their name: loaded implicitly.
 *
 * @extends JsonApiResource<\Test\app\Models\Post>
 */
final class LinkagePostResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'post';
    }

    public function toAttributes(Request $request): iterable
    {
        return [
            'title' => Schema::string()->nullable()->whenHas(),
        ];
    }

    /**
     * @return iterable<string, \Ark4ne\JsonApi\Descriptors\Schemas\RelationshipSchema<\Test\app\Models\Post>>
     */
    protected function toRelationships(Request $request): iterable
    {
        return [
            'user' => Schema::relationship($this->one(LinkageUserResource::class)),
            'comments' => Schema::relationship($this->many(LinkageCommentResource::class)),
        ];
    }
}
