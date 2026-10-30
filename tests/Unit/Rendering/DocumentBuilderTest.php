<?php

declare(strict_types=1);

namespace Test\Unit\Rendering;

use Ark4ne\JsonApi\Rendering\DocumentBuilder;
use Ark4ne\JsonApi\Rendering\ResolvedRelationship;
use Ark4ne\JsonApi\Rendering\ResourceObjectRenderer;
use PHPUnit\Framework\TestCase;
use Test\Unit\Rendering\Fixtures\Contexts;
use Test\Unit\Rendering\Fixtures\FakeRelationship;
use Test\Unit\Rendering\Fixtures\FakeResource;

final class DocumentBuilderTest extends TestCase
{
    public function test_a_resource_document_has_its_included_resources_and_document_meta(): void
    {
        $user = new FakeResource('user', 1, ['name' => 'Ada'], [
            'boss' => new FakeRelationship(ResolvedRelationship::toOne(new FakeResource('user', 2, ['name' => 'Alan']))),
        ], documentMeta: ['version' => static fn() => 2]);

        $document = (new DocumentBuilder())->resource($user, Contexts::of('include=boss'));

        self::assertSame([
            'data' => [
                'type' => 'user',
                'id' => '1',
                'attributes' => ['name' => 'Ada'],
                'relationships' => ['boss' => ['data' => ['type' => 'user', 'id' => '2']]],
            ],
            'included' => [['type' => 'user', 'id' => '2', 'attributes' => ['name' => 'Alan']]],
            'meta' => ['version' => 2],
        ], $document->toArray());
    }

    public function test_a_missing_resource_gives_null_data(): void
    {
        self::assertSame(['data' => null], (new DocumentBuilder())->resource(null, Contexts::of())->toArray());
    }

    public function test_a_collection_never_includes_its_primary_resources(): void
    {
        $friendOf = static fn(int $id, int $friend) => new FakeResource('user', $id, relationships: [
            'friend' => new FakeRelationship(ResolvedRelationship::toOne(new FakeResource('user', $friend))),
        ]);

        $document = (new DocumentBuilder())->collection(
            [$friendOf(1, 2), $friendOf(2, 3)],
            Contexts::of('include=friend'),
        );

        self::assertSame(
            [
                ['type' => 'user', 'id' => '1', 'relationships' => ['friend' => ['data' => ['type' => 'user', 'id' => '2']]]],
                ['type' => 'user', 'id' => '2', 'relationships' => ['friend' => ['data' => ['type' => 'user', 'id' => '3']]]],
            ],
            $document->toArray()['data'],
        );
        self::assertSame([['type' => 'user', 'id' => '3']], $document->toArray()['included']);
    }

    public function test_an_empty_collection_gives_an_empty_list(): void
    {
        self::assertSame(['data' => []], (new DocumentBuilder())->collection([], Contexts::of())->toArray());
    }

    public function test_the_document_meta_of_every_resource_of_a_collection_are_merged(): void
    {
        $document = (new DocumentBuilder())->collection([
            new FakeResource('user', 1, documentMeta: ['a' => 1]),
            new FakeResource('user', 2, documentMeta: ['b' => 2]),
        ], Contexts::of());

        self::assertSame(['a' => 1, 'b' => 2], $document->meta);
    }

    public function test_the_first_resource_setting_a_document_meta_keeps_it(): void
    {
        $document = (new DocumentBuilder())->collection([
            new FakeResource('user', 1, documentMeta: ['shared' => 'first']),
            new FakeResource('user', 2, documentMeta: ['shared' => 'second', 'other' => 2]),
        ], Contexts::of());

        self::assertSame(['shared' => 'first', 'other' => 2], $document->meta);
    }

    public function test_the_document_meta_of_an_included_resource_is_ignored(): void
    {
        $post = new FakeResource('post', 9, documentMeta: ['from' => 'included']);
        $user = new FakeResource('user', 1, documentMeta: ['from' => 'primary'], relationships: [
            'post' => new FakeRelationship(ResolvedRelationship::toOne($post)),
        ]);

        $document = (new DocumentBuilder())->resource($user, Contexts::of('include=post'));

        self::assertSame(['from' => 'primary'], $document->meta);
        self::assertCount(1, $document->included);
    }

    public function test_the_renderer_given_renders_the_resources(): void
    {
        $renderer = new ResourceObjectRenderer();

        $document = (new DocumentBuilder($renderer))->resource(new FakeResource('user', 1, ['name' => 'Ada']), Contexts::of());

        self::assertSame(['data' => ['type' => 'user', 'id' => '1']], $document->toArray());
    }
}
