<?php

declare(strict_types=1);

namespace Test\Unit\Definition;

use Ark4ne\JsonApi\Definition\ResourceDefinition;
use Ark4ne\JsonApi\Definition\RouteNames;
use PHPUnit\Framework\TestCase;
use Test\app\Http\Resources\PostResource;

final class RouteNamesTest extends TestCase
{
    public function test_the_relationship_routes_name_the_relationship(): void
    {
        $routes = new RouteNames('posts.show', 'posts.relationships.{relationship}', 'posts.{relationship}');

        self::assertSame('posts.relationships.main-author', $routes->relationshipRoute('main-author'));
        self::assertSame('posts.main-author', $routes->relatedRoute('main-author'));
    }

    public function test_without_pattern_a_relationship_has_no_route(): void
    {
        $routes = new RouteNames('posts.show');

        self::assertNull($routes->relationshipRoute('author'));
        self::assertNull($routes->relatedRoute('author'));
    }

    public function test_the_routes_of_a_definition_survive_var_export(): void
    {
        $definition = ResourceDefinition::of(PostResource::class, 'post')
            ->routes(new RouteNames('posts.show', 'posts.relationships.{relationship}'));

        self::assertEquals($definition, eval('return ' . var_export($definition, true) . ';'));
        self::assertNull($definition->routes(null)->routes);
        self::assertNull(ResourceDefinition::of(PostResource::class, 'post')->routes);
    }
}
