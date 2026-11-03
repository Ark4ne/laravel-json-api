<?php

declare(strict_types=1);

namespace Test\Unit\Resources\Attributes;

use Ark4ne\JsonApi\Definition\RouteNames;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiRoutes;
use PHPUnit\Framework\TestCase;

final class JsonApiRoutesTest extends TestCase
{
    public function test_the_relationship_routes_are_derived_from_the_prefix_of_self(): void
    {
        self::assertEquals(
            new RouteNames('api.posts.show', 'api.posts.relationships.{relationship}', 'api.posts.{relationship}'),
            (new JsonApiRoutes(self: 'api.posts.show'))->routeNames(),
        );
    }

    public function test_declared_relationship_routes_win_and_false_renders_none(): void
    {
        self::assertEquals(
            new RouteNames('posts.show', 'posts.{relationship}.link', null),
            (new JsonApiRoutes(self: 'posts.show', relationship: 'posts.{relationship}.link', related: false))->routeNames(),
        );
    }

    public function test_nothing_is_derived_from_a_self_route_without_prefix(): void
    {
        self::assertEquals(new RouteNames('post'), (new JsonApiRoutes(self: 'post'))->routeNames());
        self::assertEquals(
            new RouteNames(null, null, 'posts.{relationship}'),
            (new JsonApiRoutes(related: 'posts.{relationship}'))->routeNames(),
        );
    }
}
