<?php

namespace Test\Unit\Descriptors;

use Ark4ne\JsonApi\Descriptors\Relations\RelationMissing;
use Ark4ne\JsonApi\Descriptors\Relations\RelationOne;
use Ark4ne\JsonApi\Resources\Relationship;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;
use stdClass;
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
    public function testIncluded($model)
    {
        $stub = new RelationOne(UserResource::class, fn() => null);

        $relation = $stub->resolveFor(new Request, $model, 'null');
        $this->assertInstanceOf(Relationship::class, $relation);
        $this->assertNull(Reflect::get($relation, 'whenIncluded'));

        $stub->whenIncluded();
        $relation = $stub->resolveFor(new Request, $model, 'null');
        $this->assertInstanceOf(Relationship::class, $relation);
        $this->assertTrue(Reflect::get($relation, 'whenIncluded'));

        $stub->whenIncluded(false);
        $relation = $stub->resolveFor(new Request, $model, 'null');
        $this->assertInstanceOf(Relationship::class, $relation);
        $this->assertFalse(Reflect::get($relation, 'whenIncluded'));

        $stub->whenIncluded(true);
        $relation = $stub->resolveFor(new Request, $model, 'null');
        $this->assertInstanceOf(Relationship::class, $relation);
        $this->assertTrue(Reflect::get($relation, 'whenIncluded'));
    }


    /**
     * @dataProvider resourceProvider
     */
    public function testMeta($model)
    {
        $stub = new RelationOne(UserResource::class, fn() => null);

        $relation = $stub->resolveFor(new Request, $model, 'null');

        $this->assertInstanceOf(Relationship::class, $relation);
        $this->assertEmpty(Reflect::get($relation, 'meta'));

        $stub->meta(fn() => ['test']);
        $relation = $stub->resolveFor(new Request, $model, 'null');

        $this->assertInstanceOf(Relationship::class, $relation);
        $this->assertInstanceOf(\Closure::class, Reflect::get($relation, 'meta'));
        $this->assertEquals(['test'], Reflect::get($relation, 'meta')());
    }

    /**
     * @dataProvider resourceProvider
     */
    public function testLinks($model)
    {
        $stub = new RelationOne(UserResource::class, fn() => null);

        $relation = $stub->resolveFor(new Request, $model, 'null');

        $this->assertInstanceOf(Relationship::class, $relation);
        $this->assertEmpty(Reflect::get($relation, 'links'));

        $stub->links(fn() => ['test']);
        $relation = $stub->resolveFor(new Request, $model, 'null');

        $this->assertInstanceOf(Relationship::class, $relation);
        $this->assertInstanceOf(\Closure::class, Reflect::get($relation, 'links'));
        $this->assertEquals(['test'], Reflect::get($relation, 'links')());
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
    public function testWhenLoaded($expectedAttr, $relation, $invokedAttr)
    {
        $mockModel = $this->getMockForAbstractClass(Model::class, mockedMethods: ['relationLoaded']);
        $mockModel->expects(self::once())->method('relationLoaded')->with($expectedAttr)->willReturn(false);

        $stub = new RelationOne(UserResource::class, $relation);
        $stub->whenLoaded();

        $check = Reflect::invoke($stub, 'check', new Request, $mockModel, $invokedAttr);
        $this->assertFalse($check);
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
    public function testWhenPivotLoaded($expectedAttr, $relation, $invokedAttr)
    {
        $model = new class extends Model {
        };

        $model->$expectedAttr = new stdClass;
        $stub = new RelationOne(UserResource::class, $relation);
        $stub->whenPivotLoaded(stdClass::class);

        $check = Reflect::invoke($stub, 'check', new Request, $model, $invokedAttr);
        $this->assertTrue($check);
    }

    /**
     * @dataProvider resourceProvider
     */
    public function testRelationMissing($model)
    {
        $missing = RelationMissing::fromRelationship(new Relationship(UserResource::class, fn() => null));

        $check = Reflect::invoke($missing, 'check', new Request, $model, 'any');
        $value = Reflect::invoke($missing, 'valueFor', new Request, $model, 'any');

        $this->assertFalse($check);
        $this->assertInstanceOf(Relationship::class, $value);
        $this->assertInstanceOf(MissingValue::class, value(Reflect::get($value, 'value')));
    }
}
