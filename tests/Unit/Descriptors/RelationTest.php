<?php

declare(strict_types=1);

namespace Test\Unit\Descriptors;

use Ark4ne\JsonApi\Definition\Cardinality;
use Ark4ne\JsonApi\Descriptors\Relations\RelationMany;
use Ark4ne\JsonApi\Descriptors\Relations\RelationMissing;
use Ark4ne\JsonApi\Descriptors\Relations\RelationOne;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Resources\Relationship;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Test\app\Http\Resources\CommentResource;
use Test\app\Http\Resources\UserCollection;
use Test\app\Http\Resources\UserResource;
use Test\Support\Reflect;
use Test\TestCase;

class RelationTest extends TestCase
{
    public static function resourceProvider(): array
    {
        return [
            [[]],
            [new class extends stdClass {}],
            [new class extends Model {}],
        ];
    }

    /**
     * @dataProvider resourceProvider
     */
    #[DataProvider('resourceProvider')]
    public function testIncluded($model): void
    {
        $stub = new RelationOne(UserResource::class, fn() => null);

        $relation = $stub->resolveFor(new Request(), $model, 'null');
        self::assertInstanceOf(Relationship::class, $relation);
        self::assertNull(Reflect::get($relation, 'whenIncluded'));

        $stub = $stub->whenIncluded();
        $relation = $stub->resolveFor(new Request(), $model, 'null');
        self::assertInstanceOf(Relationship::class, $relation);
        self::assertTrue(Reflect::get($relation, 'whenIncluded'));

        $stub = $stub->whenIncluded(false);
        $relation = $stub->resolveFor(new Request(), $model, 'null');
        self::assertInstanceOf(Relationship::class, $relation);
        self::assertFalse(Reflect::get($relation, 'whenIncluded'));

        $stub = $stub->whenIncluded(true);
        $relation = $stub->resolveFor(new Request(), $model, 'null');
        self::assertInstanceOf(Relationship::class, $relation);
        self::assertTrue(Reflect::get($relation, 'whenIncluded'));
    }


    /**
     * @dataProvider resourceProvider
     */
    #[DataProvider('resourceProvider')]
    public function testMeta($model): void
    {
        $stub = new RelationOne(UserResource::class, fn() => null);

        $relation = $stub->resolveFor(new Request(), $model, 'null');

        self::assertInstanceOf(Relationship::class, $relation);
        self::assertEmpty(Reflect::get($relation, 'meta'));

        $stub = $stub->meta(fn() => ['test']);
        $relation = $stub->resolveFor(new Request(), $model, 'null');

        self::assertInstanceOf(Relationship::class, $relation);
        self::assertInstanceOf(Closure::class, Reflect::get($relation, 'meta'));
        self::assertEquals(['test'], Reflect::get($relation, 'meta')());
    }

    /**
     * @dataProvider resourceProvider
     */
    #[DataProvider('resourceProvider')]
    public function testLinks($model): void
    {
        $stub = new RelationOne(UserResource::class, fn() => null);

        $relation = $stub->resolveFor(new Request(), $model, 'null');

        self::assertInstanceOf(Relationship::class, $relation);
        self::assertEmpty(Reflect::get($relation, 'links'));

        $stub = $stub->links(fn() => ['test']);
        $relation = $stub->resolveFor(new Request(), $model, 'null');

        self::assertInstanceOf(Relationship::class, $relation);
        self::assertInstanceOf(Closure::class, Reflect::get($relation, 'links'));
        self::assertEquals(['test'], Reflect::get($relation, 'links')());
    }

    public static function dataWhenLoaded()
    {
        return [
            ['attr', null, 'attr'],
            ['attr', fn() => null, 'attr'],
            ['field', 'field', 'attr'],
        ];
    }

    /**
     * @dataProvider dataWhenLoaded
     */
    #[DataProvider('dataWhenLoaded')]
    public function testWhenLoaded($expectedAttr, $relation, $invokedAttr): void
    {
        $mockModel = $this->getMockBuilder(Model::class)->onlyMethods(['relationLoaded'])->getMock();
        $mockModel->expects(self::once())->method('relationLoaded')->with($expectedAttr)->willReturn(false);

        $stub = new RelationOne(UserResource::class, $relation);
        $stub = $stub->whenLoaded();

        $check = Reflect::invoke($stub, 'check', new Request(), $mockModel, $invokedAttr);
        self::assertFalse($check);
    }

    public static function dataWhenPivotLoaded()
    {
        return [
            ['attr', null, 'attr'],
            ['attr', fn() => null, 'attr'],
            ['field', 'field', 'attr'],
        ];
    }

    /**
     * @dataProvider dataWhenPivotLoaded
     */
    #[DataProvider('dataWhenPivotLoaded')]
    public function testWhenPivotLoaded($expectedAttr, $relation, $invokedAttr): void
    {
        $model = new class extends Model {};

        $model->$expectedAttr = new stdClass();
        $stub = new RelationOne(UserResource::class, $relation);
        $stub = $stub->whenPivotLoaded(stdClass::class);

        $check = Reflect::invoke($stub, 'check', new Request(), $model, $invokedAttr);
        self::assertTrue($check);
    }

    /**
     * @dataProvider resourceProvider
     */
    #[DataProvider('resourceProvider')]
    public function testRelationMissing($model): void
    {
        $missing = RelationMissing::fromRelationship(new Relationship(UserResource::class, fn() => null));

        $check = Reflect::invoke($missing, 'check', new Request(), $model, 'any');
        $value = Reflect::invoke($missing, 'valueFor', new Request(), $model, 'any');

        self::assertFalse($check);
        self::assertInstanceOf(Relationship::class, $value);
        self::assertInstanceOf(MissingValue::class, value(Reflect::get($value, 'value')));
    }

    public function test_the_definition_records_the_related_resource_and_the_cardinality(): void
    {
        $one = (new RelationOne(UserResource::class, null))->definition('author');
        $many = (new RelationMany(CommentResource::class, null))->definition('comments');

        self::assertSame('author', $one->name);
        self::assertSame(UserResource::class, $one->related);
        self::assertSame(Cardinality::One, $one->cardinality);
        self::assertSame(CommentResource::class, $many->related);
        self::assertSame(Cardinality::Many, $many->cardinality);
    }

    public function test_the_definition_of_a_collection_relates_the_collected_resource(): void
    {
        self::assertSame(
            UserResource::class,
            (new RelationMany(UserCollection::class, null))->definition('users')->related,
        );
    }

    public function test_the_definition_of_a_class_that_is_not_a_resource_throws(): void
    {
        $this->expectException(DefinitionException::class);

        /** @phpstan-ignore argument.type */
        (new RelationOne(stdClass::class, null))->definition('other');
    }

    public function test_the_definition_source_is_the_relation_or_the_name(): void
    {
        self::assertSame('author', (new RelationOne(UserResource::class, null))->definition('author')->source);
        self::assertSame('user', (new RelationOne(UserResource::class, 'user'))->definition('author')->source);

        $computed = (new RelationOne(UserResource::class, static fn() => null))->definition('author');
        self::assertTrue($computed->computed);
        self::assertNull($computed->source);
    }

    /**
     * @return iterable<string, array{bool|string|array<array-key, mixed>, list<string>, list<string>}>
     */
    public static function loads(): iterable
    {
        yield 'none' => [false, [], []];
        yield 'own relation' => [true, ['author'], []];
        yield 'path' => ['author.posts', ['author.posts'], []];
        yield 'list' => [['author', 'author.posts'], ['author', 'author.posts'], []];
        yield 'constrained' => [['author' => static fn($query) => $query, 'author.posts'], ['author', 'author.posts'], ['author']];
    }

    /**
     * @param bool|string|array<array-key, mixed> $load
     * @param list<string> $paths
     * @param list<string> $constrained
     */
    #[DataProvider('loads')]
    public function test_the_definition_records_the_eager_loads_without_closures(bool|string|array $load, array $paths, array $constrained): void
    {
        $definition = (new RelationOne(UserResource::class, null))->withLoad($load)->definition('author');

        self::assertSame($paths, $definition->load->paths);
        self::assertSame($constrained, $definition->load->constrained);
    }

    public function test_the_definition_records_links_meta_and_when_included(): void
    {
        $plain = (new RelationOne(UserResource::class, null))->definition('author');
        $full = (new RelationOne(UserResource::class, null))
            ->links(static fn() => [])
            ->meta(static fn() => [])
            ->identifierMeta(static fn() => [])
            ->whenIncluded(false)
            ->definition('author');

        self::assertFalse($plain->hasLinks);
        self::assertFalse($plain->hasMeta);
        self::assertFalse($plain->hasIdentifierMeta);
        self::assertNull($plain->whenIncluded);
        self::assertTrue($full->hasLinks);
        self::assertTrue($full->hasMeta);
        self::assertTrue($full->hasIdentifierMeta);
        self::assertFalse($full->whenIncluded);
    }

    public function test_the_definition_records_conditions(): void
    {
        self::assertFalse((new RelationOne(UserResource::class, null))->definition('author')->conditional);
        self::assertTrue((new RelationOne(UserResource::class, null))->whenLoaded()->definition('author')->conditional);
        self::assertTrue((new RelationOne(UserResource::class, null))->when(true)->definition('author')->conditional);
    }

    /**
     * @return iterable<string, array{Closure(RelationOne<mixed>): RelationOne<mixed>}>
     */
    public static function modifiers(): iterable
    {
        yield 'links' => [static fn(RelationOne $relation) => $relation->links(static fn() => [])];
        yield 'meta' => [static fn(RelationOne $relation) => $relation->meta(static fn() => [])];
        yield 'identifierMeta' => [static fn(RelationOne $relation) => $relation->identifierMeta(static fn() => [])];
        yield 'whenIncluded' => [static fn(RelationOne $relation) => $relation->whenIncluded()];
        yield 'whenLoaded' => [static fn(RelationOne $relation) => $relation->whenLoaded()];
        yield 'when' => [static fn(RelationOne $relation) => $relation->when(false)];
        yield 'withLoad' => [static fn(RelationOne $relation) => $relation->withLoad(true)];
    }

    /**
     * @param Closure(RelationOne<mixed>): RelationOne<mixed> $modifier
     */
    #[DataProvider('modifiers')]
    public function test_a_modifier_returns_a_new_descriptor_and_leaves_the_original_untouched(Closure $modifier): void
    {
        $original = new RelationOne(UserResource::class, null);
        $before = $original->definition('author');

        $modified = $modifier($original);

        self::assertNotSame($original, $modified);
        self::assertEquals($before, $original->definition('author'));
    }
}
