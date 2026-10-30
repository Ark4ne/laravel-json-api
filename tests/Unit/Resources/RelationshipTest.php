<?php

declare(strict_types=1);

namespace Test\Unit\Resources;

use Ark4ne\JsonApi\Definition\Cardinality;
use Ark4ne\JsonApi\Rendering\RenderableResource;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\JsonApi\Resources\Relationship;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;
use stdClass;
use Test\TestCase;
use Test\Unit\Rendering\Fixtures\Contexts;

class RelationshipTest extends TestCase
{
    public function test_a_to_one_relationship_resolves_its_related_resource(): void
    {
        $model = (object) ['id' => 1];

        $resolved = (new Relationship(MyModelResource::class, static fn() => $model))->resolve(Contexts::of(), true);

        self::assertSame(Cardinality::One, $resolved->cardinality);
        self::assertTrue($resolved->hasData);
        self::assertInstanceOf(MyModelResource::class, $resolved->data);
        self::assertSame($model, $resolved->data->renderedModel());
    }

    public function test_a_to_many_relationship_resolves_each_related_resource(): void
    {
        $models = [(object) ['id' => 1], (object) ['id' => 2]];

        $resolved = (new Relationship(MyModelResource::class, static fn() => $models))
            ->asCollection()
            ->resolve(Contexts::of(), true);

        self::assertSame(Cardinality::Many, $resolved->cardinality);
        self::assertSame($models, array_map(
            static fn(RenderableResource $resource) => $resource->renderedModel(),
            $resolved->resources(),
        ));
    }

    public function test_links_and_meta_are_resolved_with_the_related_resource(): void
    {
        $resolved = (new Relationship(MyModelResource::class, static fn() => (object) ['id' => 7]))
            ->withLinks(static fn(MyModelResource $resource) => ['self' => 'link/' . $resource->toArray(new Request())['id']])
            ->withMeta(static fn() => ['hash' => 'azerty'])
            ->resolve(Contexts::of(), false);

        self::assertSame(['self' => 'link/7'], $resolved->links);
        self::assertSame(['hash' => 'azerty'], $resolved->meta);
    }

    public function test_the_identifier_meta_are_resolved_with_each_related_resource(): void
    {
        $meta = static fn(MyModelResource $resource) => ['position' => $resource->resource->position];

        $many = (new Relationship(MyModelResource::class, static fn() => [
            (object) ['id' => 1, 'position' => 2],
            (object) ['id' => 2, 'position' => 1],
        ]))->asCollection()->withIdentifierMeta($meta)->resolve(Contexts::of(), false);
        $one = (new Relationship(MyModelResource::class, static fn() => (object) ['id' => 1, 'position' => 3]))
            ->withIdentifierMeta($meta)
            ->resolve(Contexts::of(), false);

        self::assertSame([['position' => 2], ['position' => 1]], $many->identifierMeta);
        self::assertSame([['position' => 3]], $one->identifierMeta);
    }

    public function test_the_identifier_meta_are_not_resolved_without_data(): void
    {
        $called = false;
        $identifierMeta = static function () use (&$called): array {
            $called = true;

            return ['position' => 1];
        };

        $missing = (new Relationship(MyModelResource::class, static fn() => new MissingValue()))
            ->withIdentifierMeta($identifierMeta)
            ->resolve(Contexts::of(), true);
        $null = (new Relationship(MyModelResource::class, static fn() => null))
            ->withIdentifierMeta($identifierMeta)
            ->resolve(Contexts::of(), true);

        self::assertSame([], $missing->identifierMeta);
        self::assertSame([], $null->identifierMeta);
        self::assertFalse($called);
    }

    public function test_a_missing_value_leaves_the_data_out_and_keeps_links_and_meta(): void
    {
        $resolved = (new Relationship(MyModelResource::class, static fn() => new MissingValue()))
            ->withLinks(static fn() => ['self' => 'link'])
            ->resolve(Contexts::of(), true);

        self::assertFalse($resolved->hasData);
        self::assertSame(['self' => 'link'], $resolved->links);
    }

    public function test_a_null_to_one_relationship_has_null_data(): void
    {
        $resolved = (new Relationship(MyModelResource::class, static fn() => null))->resolve(Contexts::of(), true);

        self::assertTrue($resolved->hasData);
        self::assertNull($resolved->data);
    }

    public function test_a_relationship_resolved_only_when_included_is_not_read_otherwise(): void
    {
        $read = false;
        $relationship = (new Relationship(MyModelResource::class, static function () use (&$read) {
            $read = true;

            return (object) ['id' => 1];
        }))->whenIncluded();

        self::assertFalse($relationship->resolve(Contexts::of(), false)->hasData);
        self::assertFalse($read);
        self::assertTrue($relationship->resolve(Contexts::of(), true)->hasData);
    }
}

/**
 * @extends JsonApiResource<stdClass>
 */
final class MyModelResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'my-model';
    }
}
