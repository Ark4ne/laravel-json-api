<?php

declare(strict_types=1);

namespace Test\Feature\Links;

use Ark4ne\JsonApi\Definition\RouteNames;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Test\app\Models\Post;
use Test\Feature\FeatureTestCase;
use Test\Feature\Links\Fixtures\OwnLinksPostResource;
use Test\Feature\Links\Fixtures\PredefinedRoutedPostResource;
use Test\Feature\Links\Fixtures\RoutedPostResource;
use Test\Feature\Links\Fixtures\UnroutedPostResource;
use Test\Support\JsonApiSchema;

/**
 * `#[JsonApiRoutes]` generates the links of a resource with the routes of the application.
 */
final class RouteLinksTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $router = $this->app?->make(Router::class);
        self::assertInstanceOf(Router::class, $router);
        $router->get('posts/{post}', static fn() => null)->name('posts.show');
        $router->get('posts/{post}/relationships/user', static fn() => null)->name('posts.relationships.user');
        $router->get('posts/{post}/relationships/tags', static fn() => null)->name('posts.relationships.tags');
        $router->get('posts/{post}/comments', static fn() => null)->name('posts.comments');
        $router->getRoutes()->refreshNameLookups();
    }

    public function test_the_links_are_generated_with_the_routes_that_exist(): void
    {
        $post = Post::query()->firstOrFail();

        $response = RoutedPostResource::make($post)->toResponse(new Request());

        self::assertSame([], JsonApiSchema::errors($response->getContent()));

        $document = TestResponse::fromBaseResponse($response);
        $document->assertJsonPath('data.links', ['self' => "http://localhost/posts/{$post->id}"]);
        $document->assertJsonPath('data.relationships.user.links', [
            'self' => "http://localhost/posts/{$post->id}/relationships/user",
        ]);
        $document->assertJsonPath('data.relationships.comments.links', [
            'related' => "http://localhost/posts/{$post->id}/comments",
        ]);
    }

    public function test_the_links_declared_by_the_resource_win(): void
    {
        $post = Post::query()->firstOrFail();

        $relationship = TestResponse::fromBaseResponse(RoutedPostResource::make($post)->toResponse(new Request()));
        $relationship->assertJsonPath('data.relationships.tags.links', ['self' => 'https://declared.example.com']);

        $resource = TestResponse::fromBaseResponse(OwnLinksPostResource::make($post)->toResponse(new Request()));
        $resource->assertJsonPath('data.links', ['self' => 'https://own.example.com']);
    }

    public function test_every_resource_object_of_a_collection_gets_its_links(): void
    {
        $posts = Post::query()->whereKey([1, 2])->get();

        $document = TestResponse::fromBaseResponse(RoutedPostResource::collection($posts)->toResponse(new Request()));

        foreach ($posts as $index => $post) {
            $document->assertJsonPath("data.{$index}.links.self", "http://localhost/posts/{$post->id}");
        }
    }

    public function test_the_self_route_must_exist(): void
    {
        $this->expectException(RouteNotFoundException::class);

        UnroutedPostResource::make(Post::query()->firstOrFail())->toResponse(new Request());
    }

    public function test_the_definition_records_the_routes(): void
    {
        self::assertEquals(
            new RouteNames('posts.show', 'posts.relationships.{relationship}', 'posts.{relationship}'),
            RoutedPostResource::definition()->routes,
        );
    }

    public function test_a_definition_written_by_hand_keeps_the_declared_routes(): void
    {
        self::assertSame('posts.show', PredefinedRoutedPostResource::definition()->routes?->self);
    }

    public function test_check_fails_on_a_self_route_that_does_not_exist(): void
    {
        self::assertSame(0, Artisan::call('jsonapi:check', ['resources' => [RoutedPostResource::class]]));

        self::assertSame(1, Artisan::call('jsonapi:check', ['resources' => [UnroutedPostResource::class]]));
        self::assertStringContainsString('missing-route', Artisan::output());
    }
}
