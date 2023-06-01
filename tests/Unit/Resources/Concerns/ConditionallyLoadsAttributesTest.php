<?php

namespace Test\Unit\Resources\Concerns;

use Ark4ne\JsonApi\Descriptors\Relations\RelationMissing;
use Ark4ne\JsonApi\Descriptors\Relations\RelationOne;
use Ark4ne\JsonApi\Descriptors\Values\ValueMixed;
use Ark4ne\JsonApi\Resources\Concerns\ConditionallyLoadsAttributes;
use Ark4ne\JsonApi\Resources\Relationship;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MergeValue;
use Illuminate\Http\Resources\MissingValue;
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
        $this->assertEquals(new MergeValue([
            'missing.1' => new MissingValue,
            'missing.2' => new MissingValue,
        ]), $actual);
        $actual = Reflect::invoke($stub, 'applyWhen', true, [
            'present.1' => 'abc',
            'present.2' => 123,
        ]);
        $this->assertEquals(new MergeValue([
            'present.1' => 'abc',
            'present.2' => 123,
        ]), $actual);
        $actual = Reflect::invoke($stub, 'applyWhen', true, [
            'present.1' => (new ValueMixed(fn() => 'abc')),
            'present.2' => (new ValueMixed(fn() => 123)),
            'present.3' => (new RelationOne('present', fn() => 'abc')),
            'present.4' => (new RelationOne('present', fn() => 123)),
            'present.5' => (new Relationship(UserResource::class, fn() => null)),
        ]);
        $this->assertEquals(new MergeValue([
            'present.1' => (new ValueMixed(fn() => 'abc')),
            'present.2' => (new ValueMixed(fn() => 123)),
            'present.3' => (new RelationOne('present', fn() => 'abc')),
            'present.4' => (new RelationOne('present', fn() => 123)),
            'present.5' => (new Relationship(UserResource::class, fn() => null)),
        ]), $actual);
        $actual = Reflect::invoke($stub, 'applyWhen', false, [
            'missing.1' => (new ValueMixed(fn() => 'abc')),
            'missing.2' => (new ValueMixed(fn() => 123)),
            'missing.3' => (new RelationOne('present', fn() => 'abc')),
            'missing.4' => (new RelationOne('present', fn() => 123)),
            'missing.5' => (new Relationship(UserResource::class, fn() => null)),
        ]);
        $this->assertEquals(new MergeValue([
            'missing.1' => new MissingValue,
            'missing.2' => new MissingValue,
            'missing.3' => (new RelationOne('present', fn() => 'abc'))->when(false),
            'missing.4' => (new RelationOne('present', fn() => 123))->when(false),
            'missing.5' => RelationMissing::fromRelationship((new Relationship(UserResource::class, fn() => null))),
        ]), $actual);
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
