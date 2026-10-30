<?php

declare(strict_types=1);

namespace Test\Unit\Rendering;

use Ark4ne\JsonApi\Rendering\IncludedRegistry;
use Ark4ne\JsonApi\Rendering\ResolvedRelationship;
use Ark4ne\JsonApi\Rendering\ResourceObject;
use Ark4ne\JsonApi\Rendering\ResourceObjectRenderer;
use PHPUnit\Framework\TestCase;
use Test\Unit\Rendering\Fixtures\Contexts;
use Test\Unit\Rendering\Fixtures\FakeRelationship;
use Test\Unit\Rendering\Fixtures\FakeResource;

final class IncludedRegistryTest extends TestCase
{
    public function test_a_resource_is_included_once_in_insertion_order(): void
    {
        $registry = new IncludedRegistry(ResourceObjectRenderer::default());
        $context = Contexts::of('include=posts')->withPath('posts');

        $registry->include(new FakeResource('post', 2), $context);
        $registry->include(new FakeResource('post', 1), $context);
        $registry->include(new FakeResource('post', 2), $context);

        self::assertSame(['post:2', 'post:1'], self::keys($registry));
    }

    public function test_a_primary_resource_is_never_included_but_its_own_includes_are(): void
    {
        $registry = new IncludedRegistry(ResourceObjectRenderer::default());
        $registry->primary('user:1');
        $user = new FakeResource('user', 1, relationships: [
            'boss' => new FakeRelationship(ResolvedRelationship::toOne(new FakeResource('user', 2))),
        ]);

        $registry->include($user, Contexts::of('include=friends.boss')->withPath('friends'));

        self::assertSame(['user:2'], self::keys($registry));
    }

    public function test_a_resource_reached_through_two_paths_keeps_the_relationships_rendered_on_each(): void
    {
        $registry = new IncludedRegistry(ResourceObjectRenderer::default());
        $context = Contexts::of('include=author.boss,editor');
        $user = static fn() => new FakeResource('user', 9, relationships: [
            'boss' => new FakeRelationship(ResolvedRelationship::toOne(new FakeResource('user', 10))),
        ]);

        $registry->include($user(), $context->withPath('editor'));
        $registry->include($user(), $context->withPath('author'));

        self::assertSame(['user:9', 'user:10'], self::keys($registry));
        self::assertSame(
            ['boss' => ['data' => ['type' => 'user', 'id' => '10']]],
            $registry->all()[0]->relationships,
        );
    }

    public function test_a_resource_already_rendered_on_a_path_is_not_rendered_again(): void
    {
        $registry = new IncludedRegistry(ResourceObjectRenderer::default());
        $relationship = new FakeRelationship(ResolvedRelationship::toOne(null));
        $context = Contexts::of('include=posts')->withPath('posts');

        $registry->include(new FakeResource('post', 1, relationships: ['author' => $relationship]), $context);
        $registry->include(new FakeResource('post', 1, relationships: ['author' => $relationship]), $context);

        self::assertCount(1, $relationship->resolutions);
    }

    /**
     * @return list<string>
     */
    private static function keys(IncludedRegistry $registry): array
    {
        return array_map(static fn(ResourceObject $object) => $object->key(), $registry->all());
    }
}
