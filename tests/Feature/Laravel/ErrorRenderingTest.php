<?php

declare(strict_types=1);

namespace Test\Feature\Laravel;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Exceptions\InvalidConfigurationException;
use Ark4ne\JsonApi\Http\Errors\ErrorRenderers;
use Ark4ne\JsonApi\Laravel\JsonApi;
use Ark4ne\JsonApi\Laravel\JsonApiServiceProvider;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Response;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use LogicException;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Test\app\Models\Post;
use Test\app\Models\User;
use Test\Feature\FeatureTestCase;
use Test\Feature\Laravel\Fixtures\ConflictErrorRenderer;
use Test\Feature\Laravel\Fixtures\PaymentRequired;
use Test\Feature\Laravel\Fixtures\RenderableException;
use Throwable;

/**
 * `JsonApi::renderErrors()` in the exception handler of the application: every error document is validated against the
 * JSON:API schema by `FeatureTestCase`.
 */
final class ErrorRenderingTest extends FeatureTestCase
{
    private const JSONAPI = ['Accept' => 'application/vnd.api+json'];

    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);

        $router->post('errors/validation', static fn() => Validator::make([], ['data.attributes.title' => 'required'])->validate());
        $router->get('errors/authentication', static fn() => throw new AuthenticationException());
        $router->get('errors/authorization', static fn() => throw new AuthorizationException('Not yours.'));
        $router->get('errors/hidden', static fn() => Response::denyAsNotFound()->authorize());
        $router->get('errors/model', static fn() => Post::query()->findOrFail(999));
        $router->get('errors/abort', static fn() => abort(429, 'Slow down.', ['Retry-After' => '60']));
        $router->get('errors/exception', static fn() => throw new RuntimeException('Secret'));
        $router->get('errors/payment', static fn() => throw new PaymentRequired());
        $router->get('errors/renderable', static fn() => throw new RenderableException());
    }

    private function renderErrors(?Closure $when = null): void
    {
        JsonApi::renderErrors($this->exceptions(), $when);
    }

    private function exceptions(): Exceptions
    {
        $handler = $this->app?->get(ExceptionHandler::class);
        self::assertInstanceOf(Handler::class, $handler);

        return new Exceptions($handler);
    }

    public function test_without_render_errors_laravel_renders_the_exceptions(): void
    {
        $response = $this->getJson('errors/authorization', self::JSONAPI);

        $response->assertStatus(403)->assertHeader('Content-Type', 'application/json');
        $response->assertJsonMissingPath('errors');
    }

    public function test_a_validation_exception_is_a_422_pointing_to_the_member(): void
    {
        $this->renderErrors();

        $this->postJson('errors/validation', [], self::JSONAPI)
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'application/vnd.api+json')
            ->assertExactJson([
                'jsonapi' => ['version' => '1.1'],
                'errors' => [[
                    'status' => '422',
                    'title' => 'Unprocessable Content',
                    'detail' => 'The data.attributes.title field is required.',
                    'source' => ['pointer' => '/data/attributes/title'],
                ]],
            ]);
    }

    public function test_an_invalid_include_names_the_parameter(): void
    {
        $this->renderErrors();
        $id = User::factory()->createOne()->getKey();
        self::assertIsInt($id);

        $this->getJson("user/{$id}?include=unknown", self::JSONAPI)
            ->assertStatus(422)
            ->assertJsonPath('errors.0.source', ['parameter' => 'include'])
            ->assertJsonPath('errors.1.detail', '"user" doesn\'t have relationship "unknown".');
    }

    public function test_an_authentication_exception_is_a_401(): void
    {
        $this->renderErrors();

        $this->getJson('errors/authentication', self::JSONAPI)
            ->assertStatus(401)
            ->assertJsonPath('errors', [['status' => '401', 'title' => 'Unauthorized', 'detail' => 'Unauthenticated.']]);
    }

    public function test_an_authorization_exception_is_a_403(): void
    {
        $this->renderErrors();

        $this->getJson('errors/authorization', self::JSONAPI)
            ->assertStatus(403)
            ->assertJsonPath('errors', [['status' => '403', 'title' => 'Forbidden', 'detail' => 'Not yours.']]);
    }

    public function test_a_denial_as_not_found_reads_as_a_missing_model(): void
    {
        $this->renderErrors();

        $hidden = $this->getJson('errors/hidden', self::JSONAPI)->assertStatus(404)->json('errors');
        $missing = $this->getJson('errors/model', self::JSONAPI)->assertStatus(404)->json('errors');

        self::assertSame([['status' => '404', 'title' => 'Not Found']], $missing);
        self::assertSame($missing, $hidden);
    }

    public function test_an_http_exception_keeps_its_status_and_its_headers(): void
    {
        $this->renderErrors();

        $this->getJson('errors/abort', self::JSONAPI)
            ->assertStatus(429)
            ->assertHeader('Retry-After', '60')
            ->assertJsonPath('errors', [['status' => '429', 'title' => 'Too Many Requests', 'detail' => 'Slow down.']]);
    }

    public function test_an_unexpected_exception_is_a_500_without_its_message(): void
    {
        $this->renderErrors();

        $this->getJson('errors/exception', self::JSONAPI)
            ->assertStatus(500)
            ->assertJsonPath('errors', [['status' => '500', 'title' => 'Internal Server Error']]);
    }

    #[DefineEnvironment('debug')]
    public function test_in_debug_an_unexpected_exception_has_its_message(): void
    {
        $this->renderErrors();

        $this->getJson('errors/exception', self::JSONAPI)
            ->assertStatus(500)
            ->assertJsonPath('errors.0.detail', 'Secret')
            ->assertJsonPath('errors.0.meta.exception', RuntimeException::class);
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function debug($app): void
    {
        $app['config']->set('app.debug', true);
    }

    public function test_a_request_that_does_not_expect_json_is_left_to_laravel(): void
    {
        $this->renderErrors();

        $response = $this->get('errors/authorization', ['Accept' => 'text/html']);

        $response->assertStatus(403);
        self::assertStringNotContainsString('application/vnd.api+json', (string) $response->headers->get('Content-Type'));
    }

    public function test_the_requests_rendered_are_those_the_condition_accepts(): void
    {
        $this->renderErrors(static fn(Request $request, Throwable $e): bool => $request->is('errors/*'));

        $this->get('errors/authorization', ['Accept' => 'text/html'])
            ->assertStatus(403)
            ->assertHeader('Content-Type', 'application/vnd.api+json');
        $this->getJson('user/999', self::JSONAPI)->assertHeader('Content-Type', 'application/json');
    }

    public function test_a_render_callback_registered_before_wins_and_one_registered_after_never_runs(): void
    {
        $this->exceptions()->render(static fn(PaymentRequired $e) => new JsonResponse(['paid' => false], 402));
        $this->renderErrors();
        $this->exceptions()->render(static fn(RuntimeException $e) => new JsonResponse(['after' => true], 400));

        $this->getJson('errors/payment', self::JSONAPI)->assertStatus(402)->assertExactJson(['paid' => false]);
        $this->getJson('errors/exception', self::JSONAPI)->assertStatus(500)->assertJsonMissingPath('after');
    }

    public function test_an_exception_rendering_itself_is_not_rendered_by_the_package(): void
    {
        $this->renderErrors();

        $this->getJson('errors/renderable', self::JSONAPI)->assertStatus(418)->assertExactJson(['rendered' => 'itself']);
    }

    public function test_the_condition_receives_the_exception_laravel_prepared(): void
    {
        $this->renderErrors(static fn(Request $request, Throwable $e): bool => $e instanceof NotFoundHttpException);

        $this->getJson('errors/model', self::JSONAPI)->assertStatus(404)->assertHeader('Content-Type', 'application/vnd.api+json');
    }

    public function test_renderers_that_cannot_be_built_leave_the_exception_to_laravel(): void
    {
        $this->app?->bind(ErrorRenderers::class, static fn() => throw new LogicException('Broken configuration.'));
        $this->renderErrors();

        $this->getJson('errors/authorization', self::JSONAPI)->assertStatus(403)->assertHeader('Content-Type', 'application/json');
    }

    #[DefineEnvironment('configuredRenderer')]
    public function test_the_configured_renderers_are_built_by_the_container_and_asked_first(): void
    {
        $this->app?->instance(ConflictErrorRenderer::class, new ConflictErrorRenderer('Configured.'));
        $this->renderErrors();

        $this->getJson('errors/exception', self::JSONAPI)
            ->assertStatus(409)
            ->assertJsonPath('errors', [['status' => '409', 'title' => 'Conflict', 'detail' => 'Configured.']]);
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function configuredRenderer($app): void
    {
        $app['config']->set('jsonapi.errors.renderers', [ConflictErrorRenderer::class]);
    }

    public function test_a_configured_class_that_is_not_an_error_renderer_fails_at_boot(): void
    {
        $this->app?->make('config')->set('jsonapi.errors.renderers', [PaymentRequired::class]);
        $this->app?->forgetInstance(Configuration::class);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('errors.renderers');

        $app = $this->app;
        self::assertNotNull($app);
        (new JsonApiServiceProvider($app))->boot();
    }

    public function test_the_renderers_are_those_of_the_container(): void
    {
        $this->app?->instance(ErrorRenderers::class, new ErrorRenderers(null));
        $this->renderErrors();

        // No renderer: Laravel renders it.
        $this->getJson('errors/authorization', self::JSONAPI)->assertStatus(403)->assertHeader('Content-Type', 'application/json');
    }
}
