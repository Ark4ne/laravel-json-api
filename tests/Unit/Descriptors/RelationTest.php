<?php

declare(strict_types=1);

namespace Test\Unit\Descriptors;

use Ark4ne\JsonApi\Configuration\Linkage;
use Ark4ne\JsonApi\Definition\Cardinality;
use Ark4ne\JsonApi\Descriptors\Relations\RelationMany;
use Ark4ne\JsonApi\Descriptors\Relations\RelationOne;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Resources\Relationship;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Facades\Gate;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Test\app\Http\Resources\CommentResource;
use Test\app\Http\Resources\UserCollection;
use Test\app\Http\Resources\UserResource;
use Test\app\Models\Comment;
use Test\app\Models\Post;
use Test\app\Models\User;
use Test\Support\Reflect;
use Test\TestCase;
use Test\Unit\Rendering\Fixtures\Contexts;

class RelationTest extends TestCase
{
    /**
     * @return list<array{mixed}>
     */
    public static function resourceProvider(): array
    {
        return [
            [[]],
            [new class extends stdClass {}],
            [new class extends Model {}],
        ];
    }

    #[DataProvider('resourceProvider')]
    public function test_when_included_sets_the_linkage_of_the_relationship(mixed $model): void
    {
        $stub = new RelationOne(UserResource::class, fn() => null);

        self::assertNull($stub->resolveFor(new Request(), $model, 'null')->linkage);

        $stub = $stub->whenIncluded();
        self::assertSame(Linkage::Included, $stub->resolveFor(new Request(), $model, 'null')->linkage);

        $stub = $stub->whenIncluded(false);
        self::assertSame(Linkage::Always, $stub->resolveFor(new Request(), $model, 'null')->linkage);

        $stub = $stub->whenIncluded(true);
        self::assertSame(Linkage::Included, $stub->resolveFor(new Request(), $model, 'null')->linkage);
    }

    #[DataProvider('resourceProvider')]
    public function test_the_meta_and_links_closures_are_declared_for_the_relationship(mixed $model): void
    {
        $stub = new RelationOne(UserResource::class, fn() => null);

        $declared = $stub->resolveFor(new Request(), $model, 'null');
        self::assertNull($declared->meta);
        self::assertNull($declared->links);

        $declared = $stub->meta(fn() => ['meta'])->links(fn() => ['links'])->resolveFor(new Request(), $model, 'null');
        self::assertSame(['meta'], ($declared->meta ?? self::fail())());
        self::assertSame(['links'], ($declared->links ?? self::fail())());
    }

    #[DataProvider('resourceProvider')]
    public function test_the_related_value_is_read_only_when_the_conditions_pass(mixed $model): void
    {
        $stub = new RelationOne(UserResource::class, fn() => 'related');

        self::assertSame('related', ($stub->resolveFor(new Request(), $model, 'any')->value)());
        self::assertInstanceOf(MissingValue::class, ($stub->when(false)->resolveFor(new Request(), $model, 'any')->value)());
    }

    public function test_the_declared_relationship_knows_its_resource_and_cardinality(): void
    {
        $one = (new RelationOne(UserResource::class, null))->resolveFor(new Request(), [], 'author');
        $many = (new RelationMany(CommentResource::class, null))->resolveFor(new Request(), [], 'comments');

        self::assertSame(UserResource::class, $one->related);
        self::assertSame(Cardinality::One, $one->cardinality);
        self::assertSame(CommentResource::class, $many->related);
        self::assertSame(Cardinality::Many, $many->cardinality);
    }

    public function test_an_unresolved_related_resource_cannot_be_declared(): void
    {
        $this->expectException(LogicException::class);

        (new RelationOne(null, null))->resolveFor(new Request(), [], 'author');
    }

    /**
     * @return list<array{string, string|Closure|null, string}>
     */
    public static function dataWhenLoaded(): array
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
    public function test_when_loaded_checks_the_relation_the_relationship_reads(string $expectedAttr, string|Closure|null $relation, string $invokedAttr): void
    {
        $mockModel = $this->getMockBuilder(Model::class)->onlyMethods(['relationLoaded'])->getMock();
        $mockModel->expects(self::once())->method('relationLoaded')->with($expectedAttr)->willReturn(false);

        $stub = new RelationOne(UserResource::class, $relation);
        $stub = $stub->whenLoaded();

        $check = Reflect::invoke($stub, 'check', new Request(), $mockModel, $invokedAttr);
        self::assertFalse($check);
    }

    /**
     * @return list<array{string, string|Closure|null, string}>
     */
    public static function dataWhenPivotLoaded(): array
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
    public function test_when_pivot_loaded_checks_the_pivot_of_the_relationship(string $expectedAttr, string|Closure|null $relation, string $invokedAttr): void
    {
        $model = new class extends Model {};

        $model->setAttribute($expectedAttr, new stdClass());
        $stub = new RelationOne(UserResource::class, $relation);
        $stub = $stub->whenPivotLoaded(stdClass::class);

        $check = Reflect::invoke($stub, 'check', new Request(), $model, $invokedAttr);
        self::assertTrue($check);
    }

    public function test_when_loaded_reads_the_attribute_of_a_resource_that_is_not_eloquent(): void
    {
        $relation = (new RelationOne(UserResource::class, null))->whenLoaded();

        self::assertTrue($relation->passes(new Request(), ['author' => ['id' => 1]], 'author'));
        self::assertTrue($relation->passes(new Request(), (object) ['author' => (object) ['id' => 1]], 'author'));
        self::assertFalse($relation->passes(new Request(), ['title' => 'Hello'], 'author'));
    }

    public function test_when_pivot_loaded_reads_the_attribute_of_a_resource_that_is_not_eloquent(): void
    {
        $relation = (new RelationOne(UserResource::class, null))->whenPivotLoaded(stdClass::class, 'pivot');

        self::assertTrue($relation->passes(new Request(), ['pivot' => new stdClass()], 'tags'));
        self::assertFalse($relation->passes(new Request(), ['pivot' => 'raw'], 'tags'));
        self::assertFalse($relation->passes(new Request(), [], 'tags'));
    }

    public function test_when_pivot_loaded_reads_the_pivot_relation_of_an_eloquent_model(): void
    {
        $model = new class extends Model {};
        $model->setRelation('pivot', new stdClass());
        $relation = (new RelationOne(UserResource::class, null))->whenPivotLoaded(stdClass::class, 'pivot');

        self::assertTrue($relation->passes(new Request(), $model, 'tags'));
        self::assertFalse($relation->passes(new Request(), new class extends Model {}, 'tags'));
    }

    public function test_can_with_a_class_that_is_not_a_gate_throws(): void
    {
        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('is not a gate');

        (new RelationOne(UserResource::class, null))->can('read', gateClass: stdClass::class)
            ->passes(new Request(), [], 'author');
    }

    public function test_can_checks_the_abilities_of_a_resource_that_is_not_eloquent(): void
    {
        Gate::define('read', static fn(?object $user, mixed $post) => is_array($post) && ($post['public'] ?? false) === true);
        $relation = (new RelationOne(UserResource::class, null))->can('read');

        self::assertTrue($relation->passes(new Request(), ['public' => true], 'author'));
        self::assertFalse($relation->passes(new Request(), ['public' => false], 'author'));
    }

    public function test_the_definition_records_the_related_resource_and_the_cardinality(): void
    {
        $one = (new RelationOne(UserResource::class, null))->definition('author');
        $many = (new RelationMany(CommentResource::class, null))->definition('comments');

        self::assertSame('author', $one->name);
        self::assertSame([UserResource::class], $one->related);
        self::assertSame(Cardinality::One, $one->cardinality);
        self::assertSame([CommentResource::class], $many->related);
        self::assertSame(Cardinality::Many, $many->cardinality);
    }

    public function test_the_definition_of_a_collection_relates_the_collected_resource(): void
    {
        self::assertSame(
            [UserResource::class],
            (new RelationMany(UserCollection::class, null))->definition('users')->related,
        );
    }

    public function test_the_definition_of_a_class_that_is_not_a_resource_throws(): void
    {
        $this->expectException(DefinitionException::class);

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

    public function test_a_relationship_read_from_a_relation_loads_it_unless_told_otherwise(): void
    {
        self::assertSame(['author'], (new RelationOne(UserResource::class, null))->definition('author')->load->paths);
        self::assertSame(['user'], (new RelationOne(UserResource::class, 'user'))->definition('author')->load->paths);
        self::assertTrue((new RelationOne(UserResource::class, null))->withLoad(false)->definition('author')->load->isEmpty());
    }

    public function test_a_relationship_read_by_a_closure_loads_nothing_unless_told(): void
    {
        $relation = new RelationOne(UserResource::class, static fn() => null);

        self::assertTrue($relation->definition('author')->load->isEmpty());
        self::assertSame(['author'], $relation->withLoad(true)->definition('author')->load->paths);
    }

    public function test_when_loaded_renders_the_linkage_only_when_loaded_unless_only_when_included(): void
    {
        $relation = new RelationOne(UserResource::class, null);

        self::assertSame(Linkage::Loaded, $relation->whenLoaded()->definition('author')->linkage);
        self::assertSame(Linkage::Included, $relation->whenIncluded()->whenLoaded()->definition('author')->linkage);
        self::assertSame(Linkage::Included, $relation->whenLoaded()->whenIncluded()->definition('author')->linkage);
        self::assertSame(Linkage::Always, $relation->whenIncluded(false)->whenIncluded()->definition('author')->linkage);
        self::assertSame(Linkage::Always, $relation->linkage(Linkage::Always)->definition('author')->linkage);
    }

    /**
     * @return iterable<string, array{bool|string|array<array-key, mixed>, list<string>, list<string>}>
     */
    public static function loads(): iterable
    {
        yield 'none' => [false, [], []];
        yield 'own relation' => [true, ['user'], []];
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
        $definition = (new RelationOne(UserResource::class, 'user'))->withLoad($load)->definition('author');

        self::assertSame($paths, $definition->load->paths);
        self::assertSame($constrained, $definition->load->constrained);
    }

    public function test_the_definition_records_links_meta_and_linkage(): void
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
        self::assertNull($plain->linkage);
        self::assertTrue($full->hasLinks);
        self::assertTrue($full->hasMeta);
        self::assertTrue($full->hasIdentifierMeta);
        self::assertSame(Linkage::Always, $full->linkage);
    }

    public function test_pivot_meta_renders_the_attributes_of_the_pivot_of_each_related_model(): void
    {
        $post = (new Post())->setRelation('members', new Collection([
            (new User())->forceFill(['id' => 1])
                ->setRelation('membership', (new Pivot())->forceFill(['role' => 'owner', 'user_id' => 1])),
            (new User())->forceFill(['id' => 2])->setRelation('membership', (new Pivot())->forceFill(['user_id' => 2])),
        ]));

        $relationship = Relationship::declared((new RelationMany(UserResource::class, 'members'))
            ->pivotMeta(['role'], 'membership')
            ->resolveFor(new Request(), $post, 'members'));

        self::assertTrue($relationship->definition('members')->hasIdentifierMeta);
        self::assertSame([['role' => 'owner'], []], $relationship->resolve(Contexts::of(), true)->identifierMeta);
    }

    public function test_pivot_meta_of_a_related_resource_without_pivot_is_empty(): void
    {
        $relationship = Relationship::declared((new RelationMany(UserResource::class, 'members'))
            ->pivotMeta(['role'])
            ->resolveFor(new Request(), ['members' => [(object) ['id' => 1]]], 'members'));

        self::assertSame([[]], $relationship->resolve(Contexts::of(), true)->identifierMeta);
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

    public function test_a_polymorphic_relationship_needs_a_resource_for_a_model_class(): void
    {
        $this->expectException(DefinitionException::class);

        (new RelationOne(UserResource::class, null))->morphs([]);
    }

    public function test_the_definition_of_a_polymorphic_relationship_records_its_map(): void
    {
        $definition = (new RelationOne(UserResource::class, 'author'))
            ->morphs([User::class => UserResource::class, Comment::class => CommentResource::class])
            ->definition('author');

        self::assertSame([User::class => UserResource::class, Comment::class => CommentResource::class], $definition->morphs);
        self::assertSame([UserResource::class, CommentResource::class], $definition->related);
    }
}
