<?php

declare(strict_types=1);

namespace Test\app\Schemas;

use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Revisions\ApiRevision;

/**
 * A resource declared with the descriptors of `ark4ne/laravel-api-revisions` (docs/rfc/api-revisions-integration.md
 * D3–D5), through three revisions: a renamed attribute, one whose type changed, one added, a meta removed.
 *
 * @extends JsonApiResource<array<string, mixed>>
 */
#[JsonApiType('articles')]
final class ArticleResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'title' => Schema::string()
                ->was(ApiRevision::Labels, name: 'label')
                ->was(ApiRevision::Initial, name: 'name'),
            'views' => Schema::int()->was(ApiRevision::Initial, schema: Schema::string()),
            'summary' => Schema::string(fn(): mixed => is_string($this->resource['body'] ?? null) ? strtoupper($this->resource['body']) : null)
                ->nullable()
                ->since(ApiRevision::Labels),
        ];
    }

    protected function toResourceMeta(Request $request): iterable
    {
        return [
            'published' => Schema::bool(),
        ];
    }

    protected function toDocumentMeta(Request $request): iterable
    {
        return [
            'legacy' => Schema::bool(static fn(): bool => true)->until(ApiRevision::Labels),
        ];
    }
}
