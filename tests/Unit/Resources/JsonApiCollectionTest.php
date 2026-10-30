<?php

declare(strict_types=1);

namespace Test\Unit\Resources;

use Ark4ne\JsonApi\Resources\JsonApiCollection;
use Ark4ne\JsonApi\Resources\Resourceable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Test\Support\Stub;
use Test\TestCase;

class JsonApiCollectionTest extends TestCase
{
    public function testToArrayBasic(): void
    {
        foreach (range(1, 3) as $idx) {
            $models[] = Stub::model(['id' => $idx]);
        }

        $collection = new class (\collect($models), null) extends JsonApiCollection {};

        $request = new Request();

        $collect = [
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
        ];
        self::assertEquals($collect, $collection->toArray($request));
        self::assertEquals([], $collection->with($request));
        self::assertEquals([
            'data' => $collect,
        ], $collection->toResponse($request)->getData(true));
    }

    public function testToArrayResource(): void
    {
        foreach (range(1, 3) as $idx) {
            $resources[] = $this->getResource(Stub::model(['id' => $idx]));
        }

        $collection = new class (\collect($resources), null) extends JsonApiCollection {};

        $request = new Request();

        $collect = [
            ['type' => 'my-model', 'id' => 1],
            ['type' => 'my-model', 'id' => 2],
            ['type' => 'my-model', 'id' => 3],
        ];
        self::assertEquals($collect, $collection->toArray($request));
        self::assertEquals([
            'meta' => ['foo' => ['bar-1', 'bar-2', 'bar-3']],
            'included' => $collect,
        ], $collection->with($request));

        $collection->with = [];
        self::assertEquals([
            'data' => $collect,
            'meta' => ['foo' => ['bar-1', 'bar-2', 'bar-3']],
            'included' => $collect,
        ], $collection->toResponse($request)->getData(true));
    }

    public function testToArrayResourceMinimal(): void
    {
        foreach (range(1, 3) as $idx) {
            $resources[] = $this->getResource(Stub::model(['id' => $idx]));
        }

        $collection = new class (\collect($resources), null) extends JsonApiCollection {
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
        self::assertEquals($collect, $collection->toArray($request, true));
        self::assertEquals([
            'meta' => [
                'foo' => ['bar-1', 'bar-2', 'bar-3'],
            ],
            'other' => 'slug',
        ], $collection->with($request));
        $collection->with = [];
        self::assertEquals([
            'data' => $collect,
            'meta' => ['foo' => ['bar-1', 'bar-2', 'bar-3']],
            'other' => 'slug',
        ], $collection->toResponse($request)->getData(true));
    }

    private function getResource(Model $model)
    {
        return new class ($model) extends JsonResource implements Resourceable {
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
