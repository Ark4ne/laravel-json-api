<?php

declare(strict_types=1);

namespace Test\Unit\Laravel\Rendering;

use Ark4ne\JsonApi\Laravel\Rendering\RouteLinksStage;
use Ark4ne\JsonApi\Rendering\IncludedRegistry;
use Ark4ne\JsonApi\Rendering\RenderableResource;
use Ark4ne\JsonApi\Rendering\ResourceObject;
use Ark4ne\JsonApi\Rendering\ResourceObjectRenderer;
use Ark4ne\JsonApi\Rendering\Stage;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Routing\UrlGenerator;
use PHPUnit\Framework\TestCase;
use Test\app\Models\Post;
use Test\Feature\Links\Fixtures\RoutedPostResource;
use Test\Unit\Laravel\Rendering\Fixtures\GenericRoutedPostResource;
use Test\Unit\Rendering\Fixtures\Contexts;
use Test\Unit\Rendering\Fixtures\FakeResource;

final class RouteLinksStageTest extends TestCase
{
    private Stage $stage;

    protected function setUp(): void
    {
        $router = new Router(new Dispatcher());
        $router->get('posts/{post}', static fn() => null)->name('posts.show');
        $router->get('posts/{post}/comments', static fn() => null)->name('posts.comments');
        $router->get('posts/{post}/relationships/{relationship}', static fn() => null)->name('posts.relationships');
        $router->getRoutes()->refreshNameLookups();

        $this->stage = new RouteLinksStage(
            new UrlGenerator($router->getRoutes(), Request::create('https://api.example.com')),
            $router,
        );
    }

    public function test_the_self_link_and_the_relationship_links_whose_route_exists_are_added(): void
    {
        $object = $this->render(ResourceObject::identify('post', 1)->withRelationships([
            'comments' => ['data' => []],
            'user' => ['data' => null],
        ]));

        self::assertSame(['self' => 'https://api.example.com/posts/1'], $object->links);
        self::assertSame(
            ['comments' => ['data' => [], 'links' => ['related' => 'https://api.example.com/posts/1/comments']], 'user' => ['data' => null]],
            $object->relationships,
        );
    }

    public function test_a_link_declared_by_the_resource_wins_even_null(): void
    {
        $object = $this->render(
            ResourceObject::identify('post', 1)
                ->withLinks(['self' => null])
                ->withRelationships(['comments' => ['links' => ['related' => null]]]),
        );

        self::assertSame(['self' => null], $object->links);
        self::assertSame(['comments' => ['links' => ['related' => null]]], $object->relationships);
    }

    public function test_a_resource_without_routes_is_left_unchanged(): void
    {
        $object = ResourceObject::identify('user', 1);

        self::assertSame($object, $this->stage->render(
            $object,
            new FakeResource('user', 1),
            Contexts::of(),
            new IncludedRegistry(ResourceObjectRenderer::default()),
        ));
    }

    public function test_a_relationship_left_out_for_having_nothing_to_render_gets_its_links(): void
    {
        $object = $this->render(ResourceObject::identify('post', 1));

        self::assertSame(
            ['comments' => ['links' => ['related' => 'https://api.example.com/posts/1/comments']]],
            $object->relationships,
        );
    }

    public function test_a_relationship_not_allowed_by_the_fieldset_gets_no_links(): void
    {
        $object = $this->render(ResourceObject::identify('post', 1), query: 'fields[post]=title');

        self::assertSame([], $object->relationships);
    }

    public function test_a_route_without_the_relationship_in_its_name_receives_it_as_a_parameter_and_a_conditional_relationship_gets_no_links(): void
    {
        $object = $this->render(ResourceObject::identify('post', 1), new GenericRoutedPostResource(new Post()));

        self::assertSame(
            ['comments' => ['links' => ['self' => 'https://api.example.com/posts/1/relationships/comments']]],
            $object->relationships,
        );
    }

    private function render(ResourceObject $object, ?RenderableResource $resource = null, string $query = ''): ResourceObject
    {
        return $this->stage->render(
            $object,
            $resource ?? new RoutedPostResource(new Post()),
            Contexts::of($query),
            new IncludedRegistry(ResourceObjectRenderer::default()),
        );
    }
}
