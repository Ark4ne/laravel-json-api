<?php

declare(strict_types=1);

namespace Test\Feature\Schemas;

use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\app\Revisions\ApiRevision;
use Test\app\Schemas\ArticleResource;
use Test\Support\JsonApiSchema;
use Test\TestCase;

/**
 * The values of a resource declared with the descriptors of `ark4ne/laravel-api-revisions`, rendered by it
 * (docs/rfc/api-revisions-integration.md D6, D7, D9).
 */
final class ValuesTest extends TestCase
{
    private const ARTICLE = ['id' => 7, 'title' => 'Hello', 'views' => 12, 'body' => 'text', 'published' => true];

    /**
     * @return iterable<string, array{ApiRevision|null, array<string, mixed>}>
     */
    public static function revisions(): iterable
    {
        yield 'Initial' => [ApiRevision::Initial, ['name' => 'Hello', 'views' => '12']];
        yield 'Labels' => [ApiRevision::Labels, ['label' => 'Hello', 'views' => 12, 'summary' => 'TEXT']];
        yield 'the newest revision' => [ApiRevision::UsersReplaceTeachers, ['title' => 'Hello', 'views' => 12, 'summary' => 'TEXT']];
        yield 'no revision resolved' => [null, ['title' => 'Hello', 'views' => 12, 'summary' => 'TEXT']];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    #[DataProvider('revisions')]
    public function test_each_revision_renders_its_attributes(?ApiRevision $revision, array $attributes): void
    {
        $response = $this->render(ArticleResource::make(self::ARTICLE), $revision);

        $response->assertJsonPath('data.type', 'articles');
        $response->assertJsonPath('data.id', '7');
        $response->assertJsonPath('data.attributes', $attributes);
        $response->assertJsonPath('data.meta', ['published' => true]);
    }

    public function test_the_document_meta_are_those_of_the_revision(): void
    {
        $this->render(ArticleResource::make(self::ARTICLE), ApiRevision::Labels)->assertJsonPath('meta', ['legacy' => true]);
        $this->render(ArticleResource::make(self::ARTICLE), null)->assertJsonMissingPath('meta');
    }

    public function test_each_resource_of_a_collection_reads_its_own_model(): void
    {
        $articles = [
            ['id' => 1, 'title' => 'One', 'views' => 1, 'body' => 'first', 'published' => true],
            ['id' => 2, 'title' => 'Two', 'views' => 2, 'body' => 'second', 'published' => false],
        ];

        $response = $this->render(ArticleResource::collection($articles), ApiRevision::Labels);

        $response->assertJsonPath('data.0.attributes', ['label' => 'One', 'views' => 1, 'summary' => 'FIRST']);
        $response->assertJsonPath('data.1.attributes', ['label' => 'Two', 'views' => 2, 'summary' => 'SECOND']);
        $response->assertJsonPath('data.1.meta', ['published' => false]);
    }

    public function test_a_hook_runs_once_per_response_whatever_the_number_of_resources(): void
    {
        // The first rendering of a class also compiles it (its hooks run on a stand-in, once per process).
        $this->render(CountedResource::make(['id' => 1, 'name' => 'a']), null);
        CountedResource::$hooks = 0;

        $this->render(CountedResource::collection([['id' => 1, 'name' => 'a'], ['id' => 2, 'name' => 'b'], ['id' => 3, 'name' => 'c']]), null)
            ->assertJsonPath('data.2.attributes.name', 'c');

        self::assertSame(1, CountedResource::$hooks);

        $this->render(CountedResource::make(['id' => 1, 'name' => 'a']), null);

        self::assertSame(2, CountedResource::$hooks);
    }

    public function test_a_sparse_fieldset_is_written_in_the_names_of_the_revision(): void
    {
        $this->render(ArticleResource::make(self::ARTICLE), ApiRevision::Initial, 'fields[articles]=name')
            ->assertJsonPath('data.attributes', ['name' => 'Hello']);

        $this->render(ArticleResource::make(self::ARTICLE), ApiRevision::Initial, 'fields[articles]=title')
            ->assertJsonMissingPath('data.attributes');
    }

    public function test_a_field_left_out_by_the_sparse_fieldset_is_never_evaluated(): void
    {
        CountedResource::$sources = 0;

        $this->render(CountedResource::make(['id' => 1, 'name' => 'a']), null, 'fields[counted]=name')
            ->assertJsonPath('data.attributes', ['name' => 'a']);

        self::assertSame(0, CountedResource::$sources);

        $this->render(CountedResource::make(['id' => 1, 'name' => 'a']), null)
            ->assertJsonPath('data.attributes', ['name' => 'a', 'computed' => 'A']);

        self::assertSame(1, CountedResource::$sources);
    }

    /**
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function render(Responsable $resource, ?ApiRevision $revision, string $query = ''): TestResponse
    {
        self::useRevision($revision);

        $response = $resource->toResponse(Request::create("/?{$query}"));
        self::assertSame([], JsonApiSchema::errors((string) $response->getContent()));

        return TestResponse::fromBaseResponse($response);
    }
}

/**
 * Counts the runs of its hook and of its computed source.
 *
 * @extends JsonApiResource<array<string, mixed>>
 */
#[JsonApiType('counted')]
final class CountedResource extends JsonApiResource
{
    public static int $hooks = 0;

    public static int $sources = 0;

    public function toAttributes(Request $request): iterable
    {
        self::$hooks++;

        return [
            'name' => Schema::string(),
            'computed' => Schema::string(function (): string {
                self::$sources++;

                return is_string($this->resource['name'] ?? null) ? strtoupper($this->resource['name']) : '';
            }),
        ];
    }
}
