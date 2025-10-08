<?php

namespace Test\Unit\Resources\Concerns;

use Ark4ne\JsonApi\Descriptors\Relations\RelationOne;
use Ark4ne\JsonApi\Descriptors\Relations\RelationRaw;
use Ark4ne\JsonApi\Descriptors\Values\ValueMixed;
use Ark4ne\JsonApi\Descriptors\Values\ValueRaw;
use Ark4ne\JsonApi\Resources\Concerns\ConditionallyLoadsAttributes;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\JsonApi\Resources\Relationship;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
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
            [true, 'test', ['test']]
        ];
    }

    /**
     * @dataProvider data
     */
    #[DataProvider('data')]
    public function testWhenInclude($expected, $property, $query)
    {
        $request = new Request(['include' => implode(',', $query)]);

        $stub = new class(null) extends JsonResource {
            use ConditionallyLoadsAttributes;
        };

        $this->assertEquals(
            $expected ?: new MissingValue,
            Reflect::invoke($stub, 'whenIncluded', $request, $property, true)
        );
    }

    /**
     * @dataProvider data
     */
    #[DataProvider('data')]
    public function testWhenInFields($expected, $property, $query)
    {
        $request = new Request(['fields' => ['type' => implode(',', $query)]]);

        $stub = new class(null) extends JsonResource {
            use ConditionallyLoadsAttributes;

            protected function toType()
            {
                return 'type';
            }
        };

        $this->assertEquals(
            $expected ?: new MissingValue,
            Reflect::invoke($stub, 'whenInFields', $request, $property, true)
        );
    }

    public function testApplyWhen()
    {
        $stub = new class(null) extends JsonResource {
            use ConditionallyLoadsAttributes;
        };
        $actual = Reflect::invoke($stub, 'applyWhen', false, [
            'missing.1' => 'abc',
            'missing.2' => 123,
        ]);
        $this->assertInstanceOf(MergeValue::class, $actual);
        $this->assertInstanceOf(ValueMixed::class, $actual->data['missing.1']);
        $this->assertInstanceOf(ValueMixed::class, $actual->data['missing.2']);
        $this->assertEquals('abc', $actual->data['missing.1']->retriever()());
        $this->assertEquals(123, $actual->data['missing.2']->retriever()());
        $actual = Reflect::invoke($stub, 'applyWhen', true, [
            'present.1' => 'abc',
            'present.2' => 123,
        ]);
        $this->assertInstanceOf(ValueMixed::class, $actual->data['present.1']);
        $this->assertInstanceOf(ValueMixed::class, $actual->data['present.2']);
        $this->assertEquals('abc', $actual->data['present.1']->retriever()());
        $this->assertEquals(123, $actual->data['present.2']->retriever()());

        $actual = Reflect::invoke($stub, 'applyWhen', true, [
            'present.1' => $p1 = (new ValueMixed(fn() => 'abc')),
            'present.2' => $p2 = (new ValueMixed(fn() => 123)),
            'present.3' => $p3 = (new RelationOne('present', fn() => 'abc')),
            'present.4' => $p4 = (new RelationOne('present', fn() => 123)),
            'present.5' => $p5 = (new Relationship(UserResource::class, fn() => null)),
        ]);
        $this->assertInstanceOf(MergeValue::class, $actual);
        $this->assertEquals($p1, $actual->data['present.1']);
        $this->assertEquals($p2, $actual->data['present.2']);
        $this->assertEquals($p3, $actual->data['present.3']);
        $this->assertEquals($p4, $actual->data['present.4']);
        $this->assertInstanceOf(RelationRaw::class, $actual->data['present.5']);
        $this->assertInstanceOf(Relationship::class, $actual->data['present.5']->retriever()());

        $actual = Reflect::invoke($stub, 'applyWhen', false, [
            'missing.1' => $p1 = (new ValueMixed(fn() => 'abc')),
            'missing.2' => $p2 = (new ValueMixed(fn() => 123)),
            'missing.3' => $p3 = (new RelationOne('present', fn() => 'abc')),
            'missing.4' => $p4 = (new RelationOne('present', fn() => 123)),
            'missing.5' => (new Relationship(UserResource::class, fn() => null)),
        ]);
        $this->assertInstanceOf(MergeValue::class, $actual);
        $this->assertEquals($p1, $actual->data['missing.1']);
        $this->assertEquals($p2, $actual->data['missing.2']);
        $this->assertEquals($p3, $actual->data['missing.3']);
        $this->assertEquals($p4, $actual->data['missing.4']);
        $this->assertInstanceOf(RelationRaw::class, $actual->data['missing.5']);
        $this->assertInstanceOf(Relationship::class, $actual->data['missing.5']->retriever()());
    }

    public function testWhenHas()
    {
        $resource = new class(['a' => 1]) extends JsonResource {
            use ConditionallyLoadsAttributes;
        };
        $this->assertEquals(1, Reflect::invoke($resource, 'whenHas', 'a'));
        $this->assertEquals('abc', Reflect::invoke($resource, 'whenHas', 'a', 'abc'));
        $this->assertEquals(new MissingValue, Reflect::invoke($resource, 'whenHas', 'b'));
        $this->assertEquals(new MissingValue, Reflect::invoke($resource, 'whenHas', 'b', 'missing'));
        $this->assertEquals('missing', Reflect::invoke($resource, 'whenHas', 'b', 'abc', 'missing'));

        $resource = new class((object)['a' => 1]) extends JsonResource {
            use ConditionallyLoadsAttributes;
        };

        $this->assertEquals(1, Reflect::invoke($resource, 'whenHas', 'a'));
        $this->assertEquals('abc', Reflect::invoke($resource, 'whenHas', 'a', 'abc'));
        $this->assertEquals(new MissingValue, Reflect::invoke($resource, 'whenHas', 'b'));
        $this->assertEquals(new MissingValue, Reflect::invoke($resource, 'whenHas', 'b', 'abc'));
        $this->assertEquals('missing', Reflect::invoke($resource, 'whenHas', 'b', 'abc', 'missing'));
    }

    public function testUnless()
    {
        $resource = new class([]) extends JsonResource {
            use ConditionallyLoadsAttributes;
        };

        $this->assertEquals(new MissingValue, Reflect::invoke($resource, 'unless', true, 'a'));
        $this->assertEquals('a', Reflect::invoke($resource, 'unless', false, 'a'));
    }
}
