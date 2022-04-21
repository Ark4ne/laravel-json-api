<?php

namespace Test\Unit;

use Ark4ne\JsonApi\Resource\JsonApiCollection;
use Ark4ne\JsonApi\Resource\Resourceable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Test\Support\Stub;
use Test\TestCase;

class JsonApiCollectionTest extends TestCase
{
    public function testToArrayBasic()
    {
        foreach (range(1, 3) as $idx) {
            $models[] = Stub::model(['id' => $idx]);
        }

        $collection = new class(collect($models), null) extends JsonApiCollection {
        };

        $request = new Request();

        $collect = [
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
        ];
        $this->assertEquals($collect, $collection->toArray($request));
        $this->assertEquals([], $collection->with($request));
        $this->assertEquals([
            'data' => $collect
        ], $collection->toResponse($request)->getData(true));
    }

    public function testToArrayResource()
    {
        foreach (range(1, 3) as $idx) {
            $resources[] = $this->getResource(Stub::model(['id' => $idx]));
        }

        $collection = new class(collect($resources), null) extends JsonApiCollection {
        };

        $request = new Request();

        $collect = [
            ['type' => 'my-model', 'id' => 1],
            ['type' => 'my-model', 'id' => 2],
            ['type' => 'my-model', 'id' => 3],
        ];
        $this->assertEquals($collect, $collection->toArray($request));
        $this->assertEquals([
            'meta' => ['foo' => ['bar-1', 'bar-2', 'bar-3']],
            'included' => $collect
        ], $collection->with($request));

        $collection->with = [];
        $this->assertEquals([
            'data' => $collect,
            'meta' => ['foo' => ['bar-1', 'bar-2', 'bar-3']],
            'included' => $collect
        ], $collection->toResponse($request)->getData(true));
    }

    public function testToArrayResourceMinimal()
    {
        foreach (range(1, 3) as $idx) {
            $resources[] = $this->getResource(Stub::model(['id' => $idx]));
        }

        $collection = new class(collect($resources), null) extends JsonApiCollection {
            public function toArray($request, bool $included = true): array
            {
                $this->with = ['other' => 'slug'];
                return parent::toArray($request, false);
            }
        };

        $request = new Request();

        $collect = [
            ['type' => 'my-model', 'id' => 1],
            ['type' => 'my-model', 'id' => 2],
            ['type' => 'my-model', 'id' => 3],
        ];
        $this->assertEquals($collect, $collection->toArray($request, true));
        $this->assertEquals([
            'meta' => [
                'foo' => ['bar-1', 'bar-2', 'bar-3']
            ],
            'other' => 'slug'
        ], $collection->with($request));
        $collection->with = [];
        $this->assertEquals([
            'data' => $collect,
            'meta' => ['foo' => ['bar-1', 'bar-2', 'bar-3']],
            'other' => 'slug'
        ], $collection->toResponse($request)->getData(true));
    }

    private function getResource(Model $model)
    {
        return new class($model) extends JsonResource implements Resourceable {
            public function toArray($request, bool $minimal = false): array
            {
                $this->with['bar'] = [];
                $this->with['meta']['foo'] = "bar-{$this->id}";
                $this->with['included'][] = [
                        'type' => 'my-model',
                        'id' => $this->id,
                    ] + $this->resource->toArray();

                return [
                    'type' => 'my-model',
                    'id' => $this->id,
                ];
            }
        };
    }
}
