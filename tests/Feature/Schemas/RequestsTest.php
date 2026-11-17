<?php

declare(strict_types=1);

namespace Test\Feature\Schemas;

use Illuminate\Routing\Router;
use Test\app\Http\Requests\Schemas\ArticleStoreRequest;
use Test\app\Http\Requests\Schemas\ArticleUpdateRequest;
use Test\app\Revisions\ApiRevision;
use Test\TestCase;

/**
 * A `JsonApiFormRequest` using the `RevisionedRequest` of `ark4ne/laravel-api-revisions`
 * (docs/rfc/api-revisions-integration.md D12).
 */
final class RequestsTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        /** @var Router $router */
        $router->post('schemas/articles', static fn(ArticleStoreRequest $request): array => [
            'type' => $request->jsonApi()->type(),
            'attributes' => $request->jsonApi()->attributes(),
        ])->middleware('revision');
        $router->patch('schemas/articles', static fn(ArticleUpdateRequest $request): array => [
            'type' => $request->jsonApi()->type(),
            'id' => $request->jsonApi()->id(),
            'attributes' => $request->jsonApi()->attributes(),
            'author' => $request->jsonApi()->ids('author'),
            'clears_author' => $request->jsonApi()->hasRelationship('author') && $request->jsonApi()->relationship('author') === null,
            'tags' => $request->jsonApi()->ids('tags'),
        ])->middleware('revision');
    }

    public function test_the_body_of_the_head_is_validated_and_read(): void
    {
        $this->postJson('schemas/articles', ['data' => ['type' => 'articles', 'attributes' => ['title' => 'Hello', 'views' => 3, 'summary' => 'Short']]])
            ->assertOk()
            ->assertExactJson(['type' => 'articles', 'attributes' => ['title' => 'Hello', 'views' => 3, 'summary' => 'Short']]);
    }

    public function test_the_body_of_an_old_client_is_validated_in_its_names_and_read_in_those_of_the_head(): void
    {
        $this->postJson(
            'schemas/articles',
            ['data' => ['type' => 'articles', 'attributes' => ['name' => 'Hello', 'views' => '3']]],
            ['Api-Version' => ApiRevision::Initial->value],
        )
            ->assertOk()
            ->assertExactJson(['type' => 'articles', 'attributes' => ['title' => 'Hello', 'views' => 3, 'summary' => '']]);
    }

    public function test_the_errors_of_an_old_client_point_at_the_members_it_sent(): void
    {
        $this->postJson(
            'schemas/articles',
            ['data' => ['type' => 'articles', 'attributes' => ['title' => 'Hello', 'name' => str_repeat('a', 21)]]],
            ['Api-Version' => ApiRevision::Initial->value],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['data.attributes.name'])
            ->assertJsonMissingValidationErrors(['data.attributes.title']);
    }

    public function test_the_type_and_the_required_members_are_validated(): void
    {
        $this->postJson('schemas/articles', ['data' => ['type' => 'posts', 'attributes' => ['views' => 'many']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['data.type', 'data.attributes.title', 'data.attributes.views']);
    }

    public function test_the_include_and_the_fields_are_still_validated_at_the_revision(): void
    {
        $body = ['data' => ['type' => 'articles', 'attributes' => ['name' => 'Hello']]];

        $this->postJson('schemas/articles?fields[articles]=name', $body, ['Api-Version' => ApiRevision::Initial->value])->assertOk();
        $this->postJson('schemas/articles?fields[articles]=title', $body, ['Api-Version' => ApiRevision::Initial->value])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fields']);
    }

    public function test_a_body_declared_with_the_helpers_reads_its_attributes_and_relationships(): void
    {
        $this->patchJson('schemas/articles', ['data' => [
            'type' => 'articles',
            'id' => '7',
            'attributes' => ['title' => 'Hello'],
            'relationships' => [
                'author' => ['data' => ['type' => 'users', 'id' => '3']],
                'tags' => ['data' => [['type' => 'tags', 'id' => '1'], ['type' => 'labels', 'id' => '2']]],
            ],
        ]])
            ->assertOk()
            ->assertExactJson([
                'type' => 'articles',
                'id' => '7',
                'attributes' => ['title' => 'Hello'],
                'author' => ['3'],
                'clears_author' => false,
                'tags' => ['1', '2'],
            ]);
    }

    public function test_the_helpers_leave_the_attributes_and_the_relationships_optional(): void
    {
        $this->patchJson('schemas/articles', ['data' => ['type' => 'articles', 'id' => '7']])
            ->assertOk()
            ->assertJsonPath('attributes', [])
            ->assertJsonPath('author', []);
    }

    public function test_a_to_one_relationship_is_cleared_by_a_null_linkage(): void
    {
        $this->patchJson('schemas/articles', ['data' => ['type' => 'articles', 'id' => '7', 'relationships' => ['author' => ['data' => null]]]])
            ->assertOk()
            ->assertJsonPath('clears_author', true);
    }

    public function test_a_to_many_relationship_is_cleared_by_an_empty_list(): void
    {
        $this->patchJson('schemas/articles', ['data' => ['type' => 'articles', 'id' => '7', 'relationships' => ['tags' => ['data' => []]]]])
            ->assertOk()
            ->assertJsonPath('tags', []);
    }

    public function test_a_to_many_relationship_sent_holds_its_linkage(): void
    {
        $this->patchJson('schemas/articles', ['data' => ['type' => 'articles', 'id' => '7', 'relationships' => ['tags' => ['meta' => ['a' => 1]]]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['data.relationships.tags.data']);
    }

    public function test_the_helpers_validate_the_type_of_the_resource_its_id_and_the_linkage(): void
    {
        $this->patchJson('schemas/articles', ['data' => [
            'type' => 'posts',
            'relationships' => [
                'author' => ['data' => ['type' => 'tags', 'id' => '3']],
                'tags' => ['data' => [['type' => 'tags'], ['type' => 'users', 'id' => '2']]],
            ],
        ]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'data.type',
                'data.id',
                'data.relationships.author.data.type',
                'data.relationships.tags.data.0.id',
                'data.relationships.tags.data.1.type',
            ]);

        $this->patchJson('schemas/articles', ['data' => ['type' => 'articles', 'id' => '7', 'relationships' => ['author' => [], 'tags' => ['data' => null]]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['data.relationships.author.data', 'data.relationships.tags.data']);
    }

    public function test_a_relationship_of_an_old_client_is_validated_in_its_name_and_read_in_the_one_of_the_head(): void
    {
        $headers = ['Api-Version' => ApiRevision::Initial->value];

        $this->patchJson('schemas/articles', ['data' => [
            'type' => 'articles',
            'id' => '7',
            'relationships' => ['writer' => ['data' => ['type' => 'users', 'id' => '3']]],
        ]], $headers)
            ->assertOk()
            ->assertJsonPath('author', ['3']);

        $this->patchJson('schemas/articles', ['data' => [
            'type' => 'articles',
            'id' => '7',
            'relationships' => ['writer' => ['data' => ['type' => 'users']]],
        ]], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['data.relationships.writer.data.id']);
    }
}
