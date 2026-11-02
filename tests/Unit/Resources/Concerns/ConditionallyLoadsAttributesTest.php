<?php

declare(strict_types=1);

namespace Test\Unit\Resources\Concerns;

use Ark4ne\JsonApi\Descriptors\Relations\RelationOne;
use Ark4ne\JsonApi\Descriptors\Relations\RelationRaw;
use Ark4ne\JsonApi\Descriptors\Values\ValueMixed;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\JsonApi\Resources\Relationship;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MergeValue;
use Illuminate\Http\Resources\MissingValue;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\app\Http\Resources\UserResource;
use Test\Support\Reflect;
use Test\TestCase;

final class ConditionallyLoadsAttributesTest extends TestCase
{
    /**
     * @return list<array{bool, string, list<string>}>
     */
    public static function data(): array
    {
        return [
            [false, 'test', []],
            [true, 'test', ['test']],
        ];
    }

    /**
     * @dataProvider data
     *
     * @param list<string> $query
     */
    #[DataProvider('data')]
    public function test_when_in_includes_renders_the_value_only_when_the_path_is_included(bool $expected, string $property, array $query): void
    {
        $request = new Request(['include' => implode(',', $query)]);

        $stub = new class ((object) ['id' => 1]) extends JsonApiResource {
            protected function toType(Request $request): string
            {
                return 'type';
            }

            public function toAttributes(Request $request): iterable
            {
                return ['test' => $this->whenInIncludes($request, 'test', true)];
            }
        };

        self::assertSame($expected ? ['test' => true] : null, $stub->toArray($request)['attributes'] ?? null);
    }

    /**
     * @dataProvider data
     *
     * @param list<string> $query
     */
    #[DataProvider('data')]
    public function test_when_in_fields_renders_the_value_only_when_the_field_is_requested(bool $expected, string $property, array $query): void
    {
        $request = new Request(['fields' => ['type' => implode(',', $query)]]);

        $stub = new class ((object) ['id' => 1]) extends JsonApiResource {
            protected function toType(Request $request): string
            {
                return 'type';
            }

            public function toAttributes(Request $request): iterable
            {
                return ['test' => $this->whenInFields($request, 'test', true)];
            }
        };

        self::assertSame($expected ? ['test' => true] : null, $stub->toArray($request)['attributes'] ?? null);
    }

    public function test_apply_when_marks_each_member_conditional_whatever_the_condition(): void
    {
        $stub = new class (null) extends JsonApiResource {};
        $actual = Reflect::invoke($stub, 'applyWhen', false, [
            'missing.1' => 'abc',
            'missing.2' => 123,
        ]);
        self::assertInstanceOf(MergeValue::class, $actual);
        self::assertInstanceOf(ValueMixed::class, $actual->data['missing.1']);
        self::assertInstanceOf(ValueMixed::class, $actual->data['missing.2']);
        self::assertEquals('abc', self::retrieved($actual->data['missing.1']));
        self::assertEquals(123, self::retrieved($actual->data['missing.2']));
        $actual = Reflect::invoke($stub, 'applyWhen', true, [
            'present.1' => 'abc',
            'present.2' => 123,
        ]);
        self::assertInstanceOf(MergeValue::class, $actual);
        self::assertInstanceOf(ValueMixed::class, $actual->data['present.1']);
        self::assertInstanceOf(ValueMixed::class, $actual->data['present.2']);
        self::assertEquals('abc', self::retrieved($actual->data['present.1']));
        self::assertEquals(123, self::retrieved($actual->data['present.2']));

        $actual = Reflect::invoke($stub, 'applyWhen', true, [
            'present.1' => $p1 = (new ValueMixed(fn() => 'abc')),
            'present.2' => $p2 = (new ValueMixed(fn() => 123)),
            'present.3' => $p3 = (new RelationOne(UserResource::class, fn() => 'abc')),
            'present.4' => $p4 = (new RelationOne(UserResource::class, fn() => 123)),
            'present.5' => $p5 = (new Relationship(UserResource::class, fn() => null)),
        ]);
        self::assertInstanceOf(MergeValue::class, $actual);
        $this->assertConditionalCopyOf($p1, $actual->data['present.1']);
        $this->assertConditionalCopyOf($p2, $actual->data['present.2']);
        $this->assertConditionalCopyOf($p3, $actual->data['present.3']);
        $this->assertConditionalCopyOf($p4, $actual->data['present.4']);
        self::assertInstanceOf(RelationRaw::class, $actual->data['present.5']);
        self::assertInstanceOf(Relationship::class, self::retrieved($actual->data['present.5']));

        $actual = Reflect::invoke($stub, 'applyWhen', false, [
            'missing.1' => $p1 = (new ValueMixed(fn() => 'abc')),
            'missing.2' => $p2 = (new ValueMixed(fn() => 123)),
            'missing.3' => $p3 = (new RelationOne(UserResource::class, fn() => 'abc')),
            'missing.4' => $p4 = (new RelationOne(UserResource::class, fn() => 123)),
            'missing.5' => (new Relationship(UserResource::class, fn() => null)),
        ]);
        self::assertInstanceOf(MergeValue::class, $actual);
        $this->assertConditionalCopyOf($p1, $actual->data['missing.1']);
        $this->assertConditionalCopyOf($p2, $actual->data['missing.2']);
        $this->assertConditionalCopyOf($p3, $actual->data['missing.3']);
        $this->assertConditionalCopyOf($p4, $actual->data['missing.4']);
        self::assertInstanceOf(RelationRaw::class, $actual->data['missing.5']);
        self::assertInstanceOf(Relationship::class, self::retrieved($actual->data['missing.5']));
    }

    public function test_apply_when_calls_a_closure_but_renders_another_callable_as_it_is(): void
    {
        $stub = new class (null) extends JsonApiResource {};
        $actual = Reflect::invoke($stub, 'applyWhen', true, [
            'closure' => static fn() => 'called',
            'function name' => 'trim',
        ]);
        self::assertInstanceOf(MergeValue::class, $actual);
        self::assertInstanceOf(ValueMixed::class, $actual->data['closure']);
        self::assertInstanceOf(ValueMixed::class, $actual->data['function name']);

        self::assertSame('called', $actual->data['closure']->valueFor(new Request(), null, 'closure'));
        self::assertSame('trim', $actual->data['function name']->valueFor(new Request(), null, 'function name'));
    }

    public function test_when_has_renders_the_value_only_when_the_resource_has_the_attribute(): void
    {
        $resource = new class (['a' => 1]) extends JsonApiResource {};
        self::assertEquals(1, Reflect::invoke($resource, 'whenHas', 'a'));
        self::assertEquals('abc', Reflect::invoke($resource, 'whenHas', 'a', 'abc'));
        self::assertEquals(new MissingValue(), Reflect::invoke($resource, 'whenHas', 'b'));
        self::assertEquals(new MissingValue(), Reflect::invoke($resource, 'whenHas', 'b', 'missing'));
        self::assertEquals('missing', Reflect::invoke($resource, 'whenHas', 'b', 'abc', 'missing'));

        $resource = new class ((object) ['a' => 1]) extends JsonApiResource {};

        self::assertEquals(1, Reflect::invoke($resource, 'whenHas', 'a'));
        self::assertEquals('abc', Reflect::invoke($resource, 'whenHas', 'a', 'abc'));
        self::assertEquals(new MissingValue(), Reflect::invoke($resource, 'whenHas', 'b'));
        self::assertEquals(new MissingValue(), Reflect::invoke($resource, 'whenHas', 'b', 'abc'));
        self::assertEquals('missing', Reflect::invoke($resource, 'whenHas', 'b', 'abc', 'missing'));
    }

    public function test_unless_leaves_the_value_out_when_the_condition_holds(): void
    {
        $resource = new class ([]) extends JsonApiResource {};

        self::assertEquals(new MissingValue(), Reflect::invoke($resource, 'unless', true, 'a'));
        self::assertEquals('a', Reflect::invoke($resource, 'unless', false, 'a'));
    }

    /**
     * @param \Ark4ne\JsonApi\Descriptors\Describer<mixed> $original
     *
     * Descriptors are immutable: applyWhen() gives conditional copies and leaves the originals untouched.
     */
    /**
     * The raw value a descriptor of `applyWhen()` reads.
     */
    private static function retrieved(mixed $descriptor): mixed
    {
        self::assertInstanceOf(\Ark4ne\JsonApi\Descriptors\Describer::class, $descriptor);
        $retriever = $descriptor->retriever();
        self::assertInstanceOf(Closure::class, $retriever);

        return $retriever();
    }

    /**
     * @param \Ark4ne\JsonApi\Descriptors\Describer<mixed> $original
     */
    private function assertConditionalCopyOf(\Ark4ne\JsonApi\Descriptors\Describer $original, mixed $actual): void
    {
        self::assertInstanceOf($original::class, $actual);
        self::assertNotSame($original, $actual);
        self::assertSame($original->retriever(), $actual->retriever());
        self::assertTrue($actual->isConditional());
        self::assertFalse($original->isConditional());
    }

    public function test_when_resolves_the_value_or_the_default_like_laravel(): void
    {
        $resource = new class ([]) extends JsonApiResource {};

        self::assertSame('a', Reflect::invoke($resource, 'when', true, 'a'));
        self::assertSame('a', Reflect::invoke($resource, 'when', true, static fn() => 'a'));
        self::assertEquals(new MissingValue(), Reflect::invoke($resource, 'when', false, 'a'));
        self::assertSame('b', Reflect::invoke($resource, 'when', false, 'a', 'b'));
        self::assertSame('b', Reflect::invoke($resource, 'when', false, 'a', static fn() => 'b'));
        self::assertNull(Reflect::invoke($resource, 'when', false, 'a', null));
    }
}
