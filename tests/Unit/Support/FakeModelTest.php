<?php

namespace Test\Unit\Support;

use Ark4ne\JsonApi\Support\FakeModel;
use Test\TestCase;

class FakeModelTest extends TestCase
{
    public function testFakeModel()
    {
        $model = new FakeModel;

        $this->assertFalse(isset($model->abc));
        $this->assertInstanceOf(FakeModel::class, FakeModel::abc());
        $this->assertInstanceOf(FakeModel::class, $model->abc);
        $this->assertInstanceOf(FakeModel::class, $model['abc']);
        $this->assertInstanceOf(FakeModel::class, $model->abc['def']->ghi['jkl']);
        $this->assertInstanceOf(FakeModel::class, $model->abc()['def']()->ghi['jkl']());
        $this->assertEquals(0, count($model));
        $this->assertEquals([], $model->toArray());
        $this->assertEquals([], $model->jsonSerialize());
        $this->assertEquals('[]', $model->toJson());
        $this->assertEquals('[]', json_encode($model));
        $this->assertEquals('', (string)($model));

        $model->abc = 123;
        $this->assertInstanceOf(FakeModel::class, $model->abc);
        $this->assertInstanceOf(FakeModel::class, $model['abc']);
        $this->assertFalse(isset($model->abc));
        $this->assertFalse(isset($model['abc']));
        $model['abc'] = 123;
        $this->assertInstanceOf(FakeModel::class, $model['abc']);
        $this->assertFalse(isset($model->abc));
        $this->assertFalse(isset($model['abc']));

        unset($model->abc);
        $this->assertInstanceOf(FakeModel::class, $model->abc);
        $this->assertInstanceOf(FakeModel::class, $model['abc']);

        unset($model['abc']);
        $this->assertInstanceOf(FakeModel::class, $model->abc);
        $this->assertInstanceOf(FakeModel::class, $model['abc']);

        $each = 0;
        foreach ($model as $item) {
            $each++;
        }
        $this->assertEquals(0, $each);
    }
}
