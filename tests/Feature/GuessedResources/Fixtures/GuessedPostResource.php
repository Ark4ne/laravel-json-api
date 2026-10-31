<?php

declare(strict_types=1);

namespace Test\Feature\GuessedResources\Fixtures;

use Ark4ne\JsonApi\Resources\Attributes\JsonApiModel;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * Relationships without resource class, the model declared by attribute.
 *
 * @extends JsonApiResource<\Test\Feature\GuessedResources\Fixtures\Article>
 */
#[JsonApiModel(Article::class)]
class GuessedPostResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return ['title' => $this->string()];
    }

    /**
     * @return iterable<string, \Ark4ne\JsonApi\Descriptors\Relations\Relation<mixed>>
     */
    protected function toRelationships(Request $request): iterable
    {
        return [
            'user' => $this->one(),
            'author' => $this->one('user'),
            'comments' => $this->many(),
            'writer' => $this->one(),
        ];
    }
}
