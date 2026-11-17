<?php

declare(strict_types=1);

namespace Test\app\Http\Requests\Schemas;

use Ark4ne\ApiRevisions\Laravel\Requests\RevisionedRequest;
use Ark4ne\ApiRevisions\Schema\Revisioned;
use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\JsonApi\Http\Body;
use Ark4ne\JsonApi\Http\JsonApiFormRequest;
use Test\app\Http\Resources\Revisions\UserResource;
use Test\app\Revisions\ApiRevision;
use Test\app\Schemas\ArticleResource;

/**
 * A write request whose body is declared with the helpers of a JSON:API body (`Http\Body`): the attributes and the
 * relationships only. `author` ← `writer` (`Initial`).
 */
final class ArticleUpdateRequest extends JsonApiFormRequest implements Revisioned
{
    use RevisionedRequest;

    protected string $resource = ArticleResource::class;

    /**
     * @return array<string, \Ark4ne\ApiRevisions\Schema\Descriptor<mixed>>
     */
    protected function body(): array
    {
        return $this->resourceObject(
            attributes: [
                'title' => Schema::string()->max(20)->was(ApiRevision::Initial, name: 'name'),
            ],
            relationships: [
                'author' => Body::toOne(UserResource::class)->was(ApiRevision::Initial, name: 'writer'),
                'tags' => Body::toMany('tags', 'labels'),
            ],
            id: Schema::string()->required(),
        );
    }
}
