<?php

declare(strict_types=1);

namespace Test\Unit\Rendering\Stages;

use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Definition\Cardinality;
use Ark4ne\JsonApi\Rendering\IncludedRegistry;
use Ark4ne\JsonApi\Rendering\ResolvedRelationship;
use Ark4ne\JsonApi\Rendering\ResourceObject;
use Ark4ne\JsonApi\Rendering\ResourceObjectRenderer;
use Ark4ne\JsonApi\Rendering\Stages\RelationshipsStage;
use PHPUnit\Framework\TestCase;
use Test\Unit\Rendering\Fixtures\Contexts;
use Test\Unit\Rendering\Fixtures\FakeRelationship;
use Test\Unit\Rendering\Fixtures\FakeResource;

final class RelationshipsStageTest extends TestCase
{
    public function test_an_empty_to_one_renders_data_null_and_an_empty_to_many_an_empty_list(): void
    {
        $object = self::render(new FakeResource('user', 1, relationships: [
            'boss' => new FakeRelationship(ResolvedRelationship::toOne(null)),
            'friends' => new FakeRelationship(ResolvedRelationship::toMany([])),
        ]), Contexts::of());

        self::assertSame(['boss' => ['data' => null], 'friends' => ['data' => []]], $object->relationships);
    }

    public function test_the_linkage_links_and_meta_are_rendered(): void
    {
        $object = self::render(new FakeResource('user', 1, relationships: [
            'boss' => new FakeRelationship(ResolvedRelationship::toOne(
                new FakeResource('user', 2),
                ['related' => '/users/1/boss'],
                ['since' => 2020],
            )),
            'friends' => new FakeRelationship(ResolvedRelationship::toMany([new FakeResource('user', 3), new FakeResource('user', 4)])),
        ]), Contexts::of());

        self::assertSame([
            'boss' => [
                'data' => ['type' => 'user', 'id' => '2'],
                'links' => ['related' => '/users/1/boss'],
                'meta' => ['since' => 2020],
            ],
            'friends' => ['data' => [['type' => 'user', 'id' => '3'], ['type' => 'user', 'id' => '4']]],
        ], $object->relationships);
    }

    public function test_a_relationship_without_data_renders_its_links_and_meta_or_is_left_out(): void
    {
        $object = self::render(new FakeResource('user', 1, relationships: [
            'boss' => new FakeRelationship(ResolvedRelationship::withoutData(Cardinality::One, ['related' => '/boss'])),
            'friends' => new FakeRelationship(ResolvedRelationship::withoutData(Cardinality::Many)),
        ]), Contexts::of());

        self::assertSame(['boss' => ['links' => ['related' => '/boss']]], $object->relationships);
    }

    public function test_a_relationship_is_resolved_as_included_when_its_path_is_requested(): void
    {
        $boss = new FakeRelationship(ResolvedRelationship::toOne(null));
        $friends = new FakeRelationship(ResolvedRelationship::toMany([]));

        self::render(new FakeResource('user', 1, relationships: ['boss' => $boss, 'friends' => $friends]), Contexts::of('include=boss'));

        self::assertSame([true], $boss->resolutions);
        self::assertSame([false], $friends->resolutions);
    }

    public function test_the_resources_of_an_included_relationship_go_to_the_registry_with_their_path(): void
    {
        $post = new FakeResource('post', 7, relationships: [
            'author' => new FakeRelationship(ResolvedRelationship::toOne(new FakeResource('user', 9, ['name' => 'Alan']))),
        ]);
        $resource = new FakeResource('user', 1, relationships: [
            'posts' => new FakeRelationship(ResolvedRelationship::toMany([$post])),
        ]);
        $included = new IncludedRegistry(ResourceObjectRenderer::default());

        self::render($resource, Contexts::of('include=posts.author'), $included);

        self::assertSame([
            [
                'type' => 'post',
                'id' => '7',
                'relationships' => ['author' => ['data' => ['type' => 'user', 'id' => '9']]],
            ],
            ['type' => 'user', 'id' => '9', 'attributes' => ['name' => 'Alan']],
        ], array_map(static fn(ResourceObject $object) => $object->toArray(), $included->all()));
    }

    public function test_a_relationship_not_included_adds_nothing_to_the_registry(): void
    {
        $resource = new FakeResource('user', 1, relationships: [
            'boss' => new FakeRelationship(ResolvedRelationship::toOne(new FakeResource('user', 2))),
        ]);
        $included = new IncludedRegistry(ResourceObjectRenderer::default());

        self::render($resource, Contexts::of(), $included);

        self::assertSame([], $included->all());
    }

    public function test_the_fieldset_of_the_type_restricts_the_relationships(): void
    {
        $friends = new FakeRelationship(ResolvedRelationship::toMany([]));
        $resource = new FakeResource('user', 1, relationships: [
            'boss' => new FakeRelationship(ResolvedRelationship::toOne(null)),
            'friends' => $friends,
        ]);

        $object = self::render($resource, Contexts::of('fields[user]=name,boss'));

        self::assertSame(['boss' => ['data' => null]], $object->relationships);
        self::assertSame([], $friends->resolutions);
    }

    public function test_the_keep_mode_renders_every_relationship_whatever_the_fieldset(): void
    {
        $resource = new FakeResource('user', 1, relationships: [
            'boss' => new FakeRelationship(ResolvedRelationship::toOne(null)),
            'friends' => new FakeRelationship(ResolvedRelationship::toMany([])),
        ]);

        $object = self::render($resource, Contexts::of('fields[user]=name', ['fields' => ['relationships' => 'keep']]));

        self::assertSame(['boss' => ['data' => null], 'friends' => ['data' => []]], $object->relationships);
    }

    private static function render(
        FakeResource $resource,
        JsonApiContext $context,
        ?IncludedRegistry $included = null,
    ): ResourceObject {
        return (new RelationshipsStage())->render(
            ResourceObject::identify($resource->renderType($context), $resource->renderIdentifier($context)),
            $resource,
            $context,
            $included ?? new IncludedRegistry(ResourceObjectRenderer::default()),
        );
    }
}
