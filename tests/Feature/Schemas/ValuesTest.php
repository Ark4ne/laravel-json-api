<?php

declare(strict_types=1);

namespace Test\Feature\Schemas;

use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\JsonApi\Compilation\Definitions;
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

    public function test_the_relationships_hook_runs_once_per_response_and_each_resource_reads_its_own_model(): void
    {
        $tree = static fn(int $id): array => ['id' => $id, 'parent' => ['id' => $id * 10, 'parent' => null]];
        $this->render(TreeResource::make($tree(1)), null);
        TreeResource::$hooks = 0;

        $response = $this->render(TreeResource::collection([$tree(1), $tree(2), $tree(3)]), null, 'include=parent');

        // Once for the resources of the collection and those included under `parent` alike.
        self::assertSame(1, TreeResource::$hooks);
        foreach ([1, 2, 3] as $index => $id) {
            $response->assertJsonPath("data.{$index}.relationships.parent.data.id", (string) ($id * 10));
            // A closure reading the resource itself, run after related resources of the same class were built.
            $response->assertJsonPath("data.{$index}.relationships.parent.links.self", "https://example.com/trees/{$id}/parent");
            $response->assertJsonPath("data.{$index}.relationships.parent.meta.of", $id);
            $response->assertJsonPath("included.{$index}.relationships.parent.data", null);
            $response->assertJsonPath("included.{$index}.relationships.parent.links.self", 'https://example.com/trees/' . ($id * 10) . '/parent');
        }
        // A condition is read for each resource.
        $response->assertJsonPath('data.0.relationships.odd.data', null);
        $response->assertJsonMissingPath('data.1.relationships.odd');
        $response->assertJsonPath('data.2.relationships.odd.data', null);
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

    public function test_a_field_only_in_fields_is_rendered_when_the_fieldset_names_it(): void
    {
        $resource = static fn(): SparseResource => SparseResource::make(['id' => 1, 'name' => 'a', 'secret' => 's']);

        $this->render($resource(), null)->assertJsonPath('data.attributes', ['name' => 'a']);
        $this->render($resource(), null, 'fields[sparse]=name')->assertJsonPath('data.attributes', ['name' => 'a']);
        $this->render($resource(), null, 'fields[sparse]=name,secret')->assertJsonPath('data.attributes', ['name' => 'a', 'secret' => 's']);
        $this->render($resource(), ApiRevision::Initial, 'fields[sparse]=hidden')->assertJsonPath('data.attributes', ['hidden' => 's']);
    }

    public function test_a_sparse_fieldset_names_the_members_of_an_object(): void
    {
        $resource = static fn(): NestedResource => NestedResource::make(['id' => 1, 'city' => 'Paris', 'zip' => '75001', 'lat' => 48.85]);

        $this->render($resource(), null, 'fields[nested]=address.city')
            ->assertJsonPath('data.attributes', ['address' => ['city' => 'Paris']]);
        $this->render($resource(), null, 'fields[nested]=address.position.lat,address.zip')
            ->assertJsonPath('data.attributes', ['address' => ['zip' => '75001', 'position' => ['lat' => 48.85]]]);
        $this->render($resource(), null, 'fields[nested]=address')
            ->assertJsonPath('data.attributes.address.position', ['lat' => 48.85, 'precise' => true]);
        $this->render($resource(), ApiRevision::Initial, 'fields[nested]=address.town')
            ->assertJsonPath('data.attributes', ['address' => ['town' => 'Paris']]);
    }

    public function test_a_condition_on_the_includes_is_read_where_each_resource_is_rendered(): void
    {
        $node = ['id' => 1, 'child' => ['id' => 2, 'child' => ['id' => 3, 'child' => null]]];

        $response = $this->render(NodeResource::make($node), null, 'include=child');

        // The same class at two places of the document: `child` is included under the root only.
        $response->assertJsonPath('data.attributes', ['child_included' => true]);
        $response->assertJsonPath('included.0.id', '2');
        $response->assertJsonMissingPath('included.0.attributes');
    }

    public function test_a_float_is_rounded_to_its_precision_before_its_conversion_to_an_older_revision(): void
    {
        $resource = static fn(): RatedResource => RatedResource::make(['id' => 1, 'rating' => 4.26, 'score' => 4.26, 'raw' => 4.26]);

        $this->render($resource(), null)
            ->assertJsonPath('data.attributes', ['rating' => 4.3, 'score' => 4.2, 'raw' => 4.26]);
        $this->render($resource(), ApiRevision::Initial)
            ->assertJsonPath('data.attributes', ['rating' => '4.3', 'score' => 4.2, 'raw' => 4.26]);
    }

    public function test_a_field_only_in_fields_is_sparse_in_the_definition(): void
    {
        $definition = Definitions::for(SparseResource::class, ApiRevision::UsersReplaceTeachers);

        self::assertSame(['secret'], $definition->sparse);
        self::assertTrue($definition->isSparse('secret'));
        self::assertTrue($definition->attribute('secret')?->conditional);
        self::assertFalse($definition->isSparse('name'));
        // In the names of the revision.
        self::assertSame(['hidden'], Definitions::for(SparseResource::class, ApiRevision::Initial)->sparse);
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

/**
 * @extends JsonApiResource<array<string, mixed>>
 */
#[JsonApiType('sparse')]
final class SparseResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'name' => Schema::string(),
            'secret' => Schema::string()->whenInFields()->was(ApiRevision::Initial, name: 'hidden'),
        ];
    }
}

/**
 * @extends JsonApiResource<array<string, mixed>>
 */
#[JsonApiType('rated')]
final class RatedResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'rating' => $this->float()->precision(1)->was(ApiRevision::Initial, schema: Schema::string()),
            'score' => $this->float()->precision(1)->truncate(),
            'raw' => $this->float(),
        ];
    }
}

/**
 * @extends JsonApiResource<array<string, mixed>>
 */
#[JsonApiType('nested')]
final class NestedResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'address' => Schema::object([
                'city' => Schema::string()->was(ApiRevision::Initial, name: 'town'),
                'zip' => Schema::string(),
                'position' => Schema::object([
                    'lat' => Schema::float(),
                    'precise' => Schema::bool(static fn(): bool => true),
                ]),
            ]),
        ];
    }
}

/**
 * @extends JsonApiResource<array<string, mixed>>
 */
#[JsonApiType('nodes')]
final class NodeResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'child_included' => $this->whenInIncludes($request, 'child', Schema::bool(static fn(): bool => true)),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'child' => $this->one(self::class),
        ];
    }
}

/**
 * Counts the runs of its relationships hook; its relationship reads the resource itself in its closures.
 *
 * @extends JsonApiResource<array<string, mixed>>
 */
#[JsonApiType('trees')]
final class TreeResource extends JsonApiResource
{
    public static int $hooks = 0;

    public function toAttributes(Request $request): iterable
    {
        return [];
    }

    protected function toRelationships(Request $request): iterable
    {
        self::$hooks++;

        return [
            'parent' => $this->one(self::class)
                ->links(self: fn(): string => 'https://example.com/trees/' . $this->id() . '/parent')
                ->meta(fn(): array => ['of' => $this->id()]),
            'odd' => $this->one(self::class, fn(): mixed => null)->when(fn(): bool => $this->id() % 2 === 1),
        ];
    }

    private function id(): int
    {
        $id = $this->resource['id'] ?? null;

        return is_int($id) ? $id : 0;
    }
}
