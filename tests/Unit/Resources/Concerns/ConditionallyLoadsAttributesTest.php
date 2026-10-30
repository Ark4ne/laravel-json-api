<?php

declare(strict_types=1);

namespace Test\Unit\Resources\Concerns;

use Ark4ne\JsonApi\Descriptors\Relations\RelationOne;
use Ark4ne\JsonApi\Descriptors\Relations\RelationRaw;
use Ark4ne\JsonApi\Descriptors\Values\ValueMixed;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\JsonApi\Resources\Relationship;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MergeValue;
use Illuminate\Http\Resources\MissingValue;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\app\Http\Resources\UserResource;
use Test\Support\Reflect;
use Test\TestCase;

class ConditionallyLoadsAttributesTest extends TestCase
{
    public static function data()
    {
        return [
            [false, 'test', []],
            [true, 'test', ['test']],
        ];
    }

    /**
     * @dataProvider data
     */
    #[DataProvider('data')]
    public function testWhenInclude($expected, $property, $query): void
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
     */
    #[DataProvider('data')]
    public function testWhenInFields($expected, $property, $query): void
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

    public function testApplyWhen(): void
    {
        $stub = new class (null) extends JsonApiResource {};
        $actual = Reflect::invoke($stub, 'applyWhen', false, [
            'missing.1' => 'abc',
            'missing.2' => 123,
        ]);
        self::assertInstanceOf(MergeValue::class, $actual);
        self::assertInstanceOf(ValueMixed::class, $actual->data['missing.1']);
        self::assertInstanceOf(ValueMixed::class, $actual->data['missing.2']);
        self::assertEquals('abc', $actual->data['missing.1']->retriever()());
        self::assertEquals(123, $actual->data['missing.2']->retriever()());
        $actual = Reflect::invoke($stub, 'applyWhen', true, [
            'present.1' => 'abc',
            'present.2' => 123,
        ]);
        self::assertInstanceOf(ValueMixed::class, $actual->data['present.1']);
        self::assertInstanceOf(ValueMixed::class, $actual->data['present.2']);
        self::assertEquals('abc', $actual->data['present.1']->retriever()());
        self::assertEquals(123, $actual->data['present.2']->retriever()());

        $actual = Reflect::invoke($stub, 'applyWhen', true, [
            'present.1' => $p1 = (new ValueMixed(fn() => 'abc')),
            'present.2' => $p2 = (new ValueMixed(fn() => 123)),
            'present.3' => $p3 = (new RelationOne('present', fn() => 'abc')),
            'present.4' => $p4 = (new RelationOne('present', fn() => 123)),
            'present.5' => $p5 = (new Relationship(UserResource::class, fn() => null)),
        ]);
        self::assertInstanceOf(MergeValue::class, $actual);
        $this->assertConditionalCopyOf($p1, $actual->data['present.1']);
        $this->assertConditionalCopyOf($p2, $actual->data['present.2']);
        $this->assertConditionalCopyOf($p3, $actual->data['present.3']);
        $this->assertConditionalCopyOf($p4, $actual->data['present.4']);
        self::assertInstanceOf(RelationRaw::class, $actual->data['present.5']);
        self::assertInstanceOf(Relationship::class, $actual->data['present.5']->retriever()());

        $actual = Reflect::invoke($stub, 'applyWhen', false, [
            'missing.1' => $p1 = (new ValueMixed(fn() => 'abc')),
            'missing.2' => $p2 = (new ValueMixed(fn() => 123)),
            'missing.3' => $p3 = (new RelationOne('present', fn() => 'abc')),
            'missing.4' => $p4 = (new RelationOne('present', fn() => 123)),
            'missing.5' => (new Relationship(UserResource::class, fn() => null)),
        ]);
        self::assertInstanceOf(MergeValue::class, $actual);
        $this->assertConditionalCopyOf($p1, $actual->data['missing.1']);
        $this->assertConditionalCopyOf($p2, $actual->data['missing.2']);
        $this->assertConditionalCopyOf($p3, $actual->data['missing.3']);
        $this->assertConditionalCopyOf($p4, $actual->data['missing.4']);
        self::assertInstanceOf(RelationRaw::class, $actual->data['missing.5']);
        self::assertInstanceOf(Relationship::class, $actual->data['missing.5']->retriever()());
    }

    public function testWhenHas(): void
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

    public function testUnless(): void
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
    private function assertConditionalCopyOf(\Ark4ne\JsonApi\Descriptors\Describer $original, mixed $actual): void
    {
        self::assertInstanceOf($original::class, $actual);
        self::assertNotSame($original, $actual);
        self::assertSame($original->retriever(), $actual->retriever());
        self::assertTrue($actual->isConditional());
        self::assertFalse($original->isConditional());
    }
}
