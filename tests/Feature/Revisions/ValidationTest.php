<?php

declare(strict_types=1);

namespace Test\Feature\Revisions;

use Ark4ne\JsonApi\Laravel\JsonApi;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Routing\Router;
use Test\app\Http\Middleware\UseApiRevision;
use Test\app\Http\Requests\Revisions\PostRequest;
use Test\app\Http\Resources\Revisions\PostResource;
use Test\app\Models\Post;
use Test\app\Revisions\ApiRevision;

/**
 * The query of an old client validated at its revision (docs/rfc/versioned-resources-epics.md US 5.3): the middleware
 * setting the revision runs before the form request is resolved, with the controller.
 */
final class ValidationTest extends RevisionsTestCase
{
    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);

        /** @var Router $router */
        $router->get(
            'revisions/validated/posts/{id}',
            static fn(PostRequest $request, string $id) => PostResource::make(Post::query()->findOrFail($id)),
        )->middleware(UseApiRevision::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $handler = $this->app?->get(ExceptionHandler::class);
        self::assertInstanceOf(Handler::class, $handler);
        JsonApi::renderErrors(new Exceptions($handler));
    }

    public function test_an_old_client_is_validated_at_its_revision(): void
    {
        $this->get('revisions/validated/posts/1?include=writer&fields[writers]=name', ['Api-Version' => ApiRevision::Labels->value])
            ->assertOk()
            ->assertJsonPath('included.0.type', 'writers');
        $this->get('revisions/validated/posts/1?include=user&fields[users]=email')->assertOk();
    }

    public function test_the_errors_of_an_old_client_name_the_parameter(): void
    {
        $headers = ['Accept' => 'application/vnd.api+json', 'Api-Version' => ApiRevision::Labels->value];

        $this->get('revisions/validated/posts/1?include=user', $headers)
            ->assertStatus(422)
            ->assertJsonPath('errors.0.source', ['parameter' => 'include'])
            ->assertJsonPath('errors.1.detail', '"posts" doesn\'t have relationship "user".');
        $this->get('revisions/validated/posts/1?fields[users]=email', $headers)
            ->assertStatus(422)
            ->assertJsonPath('errors.0.source', ['parameter' => 'fields'])
            ->assertJsonPath('errors.1.detail', '"users" doesn\'t exist.');
    }
}
