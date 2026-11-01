<?php

declare(strict_types=1);

namespace Test\Feature\Laravel;

use Ark4ne\JsonApi\Laravel\JsonApi;
use Ark4ne\JsonApi\Laravel\JsonApiServiceProvider;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Routing\Router;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Test\app\Http\Resources\UserResource;
use Test\app\Models\User;
use Test\Feature\FeatureTestCase;
use Test\Feature\Laravel\Fixtures\ApplicationMiddleware;

/**
 * The `jsonapi` route middleware on the application of `tests/app`, its errors rendered by `JsonApi::renderErrors()`
 * and validated against the JSON:API schema by `FeatureTestCase`.
 */
final class ContentNegotiationTest extends FeatureTestCase
{
    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);

        $router->group(['middleware' => 'jsonapi'], static function (Router $router): void {
            $router->get('negotiated/user/{id}', static fn(string $id) => UserResource::make(User::query()->findOrFail($id)));
            $router->post('negotiated/user', static fn() => UserResource::make(User::query()->firstOrFail()));
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        $handler = $this->app?->get(ExceptionHandler::class);
        self::assertInstanceOf(Handler::class, $handler);
        JsonApi::renderErrors(new Exceptions($handler));
    }

    public function test_a_route_without_the_middleware_does_not_negotiate(): void
    {
        $id = User::factory()->createOne()->getKey();
        self::assertIsInt($id);

        $this->get("user/{$id}", ['Accept' => 'application/vnd.api+json; charset=utf-8'])->assertOk();
    }

    public function test_an_acceptable_request_is_served_varying_on_accept(): void
    {
        $id = User::factory()->createOne()->getKey();
        self::assertIsInt($id);

        $this->get("negotiated/user/{$id}", ['Accept' => 'application/vnd.api+json'])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.api+json')
            ->assertHeader('Vary', 'Accept');
    }

    #[DefineEnvironment('withExtension')]
    public function test_a_resource_names_the_extensions_applied_on_any_route(): void
    {
        $id = User::factory()->createOne()->getKey();
        self::assertIsInt($id);

        $this->get("user/{$id}", ['Accept' => 'application/vnd.api+json'])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.api+json; ext="https://jsonapi.org/ext/atomic"');
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function withExtension($app): void
    {
        $app['config']->set('jsonapi.document.jsonapi.ext', ['https://jsonapi.org/ext/atomic']);
    }

    public function test_a_jsonapi_alias_of_the_application_is_kept(): void
    {
        $app = $this->app;
        self::assertNotNull($app);
        $router = $app->make(Router::class);
        $router->aliasMiddleware('jsonapi', ApplicationMiddleware::class);

        (new JsonApiServiceProvider($app))->boot();

        self::assertSame(ApplicationMiddleware::class, $router->getMiddleware()['jsonapi']);
    }

    public function test_an_accept_that_cannot_be_served_is_a_406_error_document(): void
    {
        $this->get('negotiated/user/1', ['Accept' => 'application/vnd.api+json; charset=utf-8'])
            ->assertStatus(406)
            ->assertHeader('Content-Type', 'application/vnd.api+json')
            ->assertHeader('Vary', 'Accept')
            ->assertJsonPath('errors.0.status', '406')
            ->assertJsonPath('errors.0.title', 'Not Acceptable');
    }

    public function test_a_content_type_that_cannot_be_served_is_a_415_error_document(): void
    {
        $this->call('POST', 'negotiated/user', server: [
            'CONTENT_TYPE' => 'application/vnd.api+json; ext="https://example.com/ext"',
            'HTTP_ACCEPT' => 'application/vnd.api+json',
        ], content: '{"data":{"type":"user"}}')
            ->assertStatus(415)
            ->assertJsonPath('errors.0.status', '415')
            ->assertJsonPath('errors.0.detail', 'The Content-Type of the request cannot be served: the extension "https://example.com/ext" is not supported.');
    }
}
