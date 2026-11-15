<?php

declare(strict_types=1);

namespace Test\app\Http\Requests\Schemas;

use Ark4ne\ApiRevisions\Laravel\Requests\RevisionedRequest;
use Ark4ne\ApiRevisions\Schema\Revisioned;
use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\JsonApi\Http\JsonApiFormRequest;
use Test\app\Revisions\ApiRevision;
use Test\app\Schemas\ArticleResource;

/**
 * A write request whose body is declared with the descriptors of `ark4ne/laravel-api-revisions`
 * (docs/rfc/api-revisions-integration.md D12): validated in the shape of the client's revision, read in the head's.
 * `title` ← `name` (`Initial`); `summary` added at `Labels`, empty for an older client.
 */
final class ArticleStoreRequest extends JsonApiFormRequest implements Revisioned
{
    use RevisionedRequest;

    protected string $resource = ArticleResource::class;

    /**
     * @return array<string, \Ark4ne\ApiRevisions\Schema\Descriptor<mixed>>
     */
    protected function body(): array
    {
        return [
            'data' => Schema::object([
                'type' => Schema::string()->required()->rules('in:articles'),
                'attributes' => Schema::object([
                    'title' => Schema::string()->required()->max(20)->was(ApiRevision::Initial, name: 'name'),
                    'views' => Schema::int()->was(ApiRevision::Initial, schema: Schema::string()),
                    'summary' => Schema::string()->nullable()->since(ApiRevision::Labels, default: ''),
                ])->required(),
            ])->required(),
        ];
    }
}
