<?php

declare(strict_types=1);

namespace Test\Feature\Schemas;

use Illuminate\Routing\Router;
use Test\app\Http\Requests\Schemas\ArticleStoreRequest;
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
}
