<?php

declare(strict_types=1);

namespace Test\Unit\Resources;

use Ark4ne\JsonApi\Configuration\Linkage;
use Ark4ne\JsonApi\Definition\Cardinality;
use Ark4ne\JsonApi\Filters\Filters;
use Ark4ne\JsonApi\Rendering\RenderableResource;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\JsonApi\Resources\Relationship;
use Ark4ne\JsonApi\Support\Diagnostic;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('linkages')]
    public function test_a_relationship_not_included_renders_its_linkage_as_configured(
        array $config,
        ?bool $loaded,
        bool $hasData,
    ): void {
        $relationship = new Relationship(MyModelResource::class, static fn() => (object) ['id' => 1]);

        if ($loaded !== null) {
            $relationship->loadedWhen(static fn() => $loaded);
        }

        self::assertSame($hasData, $relationship->resolve(Contexts::of('', $config), false)->hasData);
        self::assertTrue($relationship->resolve(Contexts::of('', $config), true)->hasData);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, bool|null, bool}>
     */
    public static function linkages(): iterable
    {
        yield 'always' => [[], false, true];
        yield 'loaded, loaded' => [['relationship' => ['linkage' => 'loaded']], true, true];
        yield 'loaded, not loaded' => [['relationship' => ['linkage' => 'loaded']], false, false];
        yield 'loaded, unknown' => [['relationship' => ['linkage' => 'loaded']], null, true];
        yield 'included' => [['relationship' => ['linkage' => 'included']], true, false];
    }

    public function test_the_linkage_of_a_relationship_wins_over_the_configured_one(): void
    {
        $relationship = (new Relationship(MyModelResource::class, static fn() => (object) ['id' => 1]))
            ->whenIncluded(false);

        self::assertTrue($relationship->resolve(Contexts::of('', ['relationship' => ['linkage' => 'included']]), false)->hasData);
        self::assertFalse($relationship->linkage(Linkage::Included)->resolve(Contexts::of(), false)->hasData);
    }

    public function test_filters_on_a_relation_that_is_not_loaded_give_a_diagnostic(): void
    {
        $context = Contexts::of();
        $relationship = (new Relationship(MyModelResource::class, static fn() => (object) ['id' => 1]))
            ->withFilters((new Filters())->when(static fn() => true));

        $relationship->loadedWhen(static fn() => true)->resolve($context, true);
        self::assertCount(0, $context->diagnostics);

        $relationship->loadedWhen(static fn() => false)->resolve($context, true);
        self::assertSame(
            [Relationship::FILTER_ON_UNLOADED],
            array_map(static fn(Diagnostic $diagnostic) => $diagnostic->code, $context->diagnostics->all()),
        );
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
